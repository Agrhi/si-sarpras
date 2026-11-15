<?php

namespace App\Http\Controllers\Sarpras;

use App\Enums\ApprovalDecision;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Procurement;
use App\Models\ProcurementItem;
use App\Models\User;
use App\Notifications\Sarpras\ProcurementApproved;
use App\Notifications\Sarpras\ProcurementReceived;
use App\Notifications\Sarpras\ProcurementRejected;
use App\Notifications\Sarpras\ProcurementSubmitted;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProcurementController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Display a listing of procurement requests.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search', '');
        $perPage = (int) $request->get('perPage', 10);
        $status = $request->get('status', '');

        $procurements = Procurement::query()
            ->with(['user:id,name', 'items:id,procurement_id,quantity,unit_price'])
            ->when($search, function ($query, $search) {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when(! $request->user()->can('procurement.approve'), function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Procurement $procurement) => [
                'id' => $procurement->id,
                'title' => $procurement->title,
                'proposer' => $procurement->user->name,
                'item_count' => $procurement->items->count(),
                'estimated_total' => $procurement->items->sum(fn (ProcurementItem $item) => $item->estimatedTotal()),
                'created_at' => $procurement->created_at->format('d/m/Y'),
                'status' => ['value' => $procurement->status->value, 'label' => $procurement->status->label()],
            ]);

        return Inertia::render('sarpras/procurements/index', [
            'procurements' => $procurements->items(),
            'total' => $procurements->total(),
            'page' => $procurements->currentPage(),
            'perPage' => $procurements->perPage(),
            'search' => $search,
            'status' => $status,
            'statuses' => ProcurementStatus::options(),
            'can_create' => $request->user()->can('procurement.create'),
        ]);
    }

    /**
     * Show the form for creating a procurement request.
     */
    public function create(): Response
    {
        return Inertia::render('sarpras/procurements/create', [
            'categories' => Category::orderBy('name')->get(['id', 'name'])
                ->map(fn (Category $category) => ['value' => $category->id, 'label' => $category->name]),
            'locations' => Location::orderBy('name')->get(['id', 'name'])
                ->map(fn (Location $location) => ['value' => $location->id, 'label' => $location->name]),
        ]);
    }

    /**
     * Store a newly submitted procurement request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'items.*.location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $steps = ApprovalFlow::query()
            ->where('module', 'procurement')
            ->where('is_active', true)
            ->value('steps') ?? [];

        if (empty($steps)) {
            return back()->withErrors(['items' => 'Alur persetujuan pengadaan belum dikonfigurasi.']);
        }

        $procurement = DB::transaction(function () use ($request, $validated, $steps) {
            $procurement = Procurement::create([
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'reason' => $validated['reason'],
                'status' => ProcurementStatus::Pending,
                'approval_steps' => $steps,
                'current_step' => 0,
            ]);

            foreach ($validated['items'] as $item) {
                $procurement->items()->create($item);
            }

            return $procurement;
        });

        $procurement->loadMissing('user');

        $this->notifyApprovers($procurement, $steps[0]['role'] ?? null, new ProcurementSubmitted($procurement), $request->user());

        return redirect()->route('procurements.show', $procurement)->with('success', 'Usulan pengadaan berhasil dikirim.');
    }

    /**
     * Display the specified procurement request.
     */
    public function show(Request $request, Procurement $procurement): Response
    {
        $procurement->load([
            'user:id,name',
            'items.category:id,name',
            'items.location:id,name',
            'approvals.user:id,name',
        ]);

        $user = $request->user();
        $pending = $procurement->status === ProcurementStatus::Pending;

        $steps = collect($procurement->approval_steps)->map(function (array $step, int $index) use ($procurement, $pending) {
            $approval = $procurement->approvals->firstWhere('step_index', $index);

            return [
                'title' => $step['title'],
                'role' => $step['role'],
                'state' => $procurement->stepState($index, $pending),
                'approval' => $approval ? [
                    'user' => $approval->user->name,
                    'decision' => $approval->decision->label(),
                    'note' => $approval->note,
                    'created_at' => $approval->created_at->format('d/m/Y H:i'),
                ] : null,
            ];
        });

        $canApprove = $pending
            && $user->can('procurement.approve')
            && ($role = $procurement->currentStepRole()) !== null
            && ($user->hasRole($role) || $user->hasRole('super-admin'));

        return Inertia::render('sarpras/procurements/show', [
            'procurement' => [
                'id' => $procurement->id,
                'title' => $procurement->title,
                'proposer' => $procurement->user->name,
                'reason' => $procurement->reason,
                'created_at' => $procurement->created_at->format('d/m/Y H:i'),
                'received_at' => $procurement->received_at?->format('d/m/Y H:i'),
                'status' => ['value' => $procurement->status->value, 'label' => $procurement->status->label()],
                'estimated_total' => $procurement->items->sum(fn (ProcurementItem $item) => $item->estimatedTotal()),
                'steps' => $steps,
            ],
            'items' => $procurement->items->map(fn (ProcurementItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'category' => $item->category?->name,
                'location' => $item->location?->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'estimated_total' => $item->estimatedTotal(),
                'notes' => $item->notes,
            ]),
            'can_approve' => $canApprove,
            'can_receive' => $procurement->status === ProcurementStatus::Approved && $user->can('procurement.receive'),
        ]);
    }

    /**
     * Approve the procurement request at the current step.
     */
    public function approve(Request $request, Procurement $procurement): RedirectResponse
    {
        return $this->decide($request, $procurement, ApprovalDecision::Approved);
    }

    /**
     * Reject the procurement request at the current step.
     */
    public function reject(Request $request, Procurement $procurement): RedirectResponse
    {
        return $this->decide($request, $procurement, ApprovalDecision::Rejected);
    }

    /**
     * Mark approved goods as received and register them as assets.
     */
    public function receive(Request $request, Procurement $procurement): RedirectResponse
    {
        abort_unless($procurement->status === ProcurementStatus::Approved, 409, 'Hanya pengadaan disetujui yang dapat diterima.');

        $procurement->loadMissing(['items', 'user']);

        DB::transaction(function () use ($procurement) {
            foreach ($procurement->items as $item) {
                for ($sequence = 1; $sequence <= $item->quantity; $sequence++) {
                    Asset::create([
                        'inventory_code' => sprintf(
                            'INV-%s-P%d-%d-%d',
                            now()->format('Ymd'),
                            $procurement->id,
                            $item->id,
                            $sequence,
                        ),
                        'name' => $item->name,
                        'category_id' => $item->category_id,
                        'location_id' => $item->location_id,
                        'acquisition_date' => now()->toDateString(),
                        'acquisition_cost' => $item->unit_price,
                        'condition' => AssetCondition::Baik,
                        'status' => AssetStatus::Aktif,
                        'notes' => "Pengadaan #{$procurement->id}",
                    ]);
                }
            }

            $procurement->update([
                'status' => ProcurementStatus::Received,
                'received_at' => now(),
            ]);
        });

        $procurement->loadMissing('user');
        $this->notify($procurement->user, new ProcurementReceived($procurement), $request->user());

        return redirect()->route('procurements.show', $procurement)
            ->with('success', 'Barang diterima dan tercatat sebagai aset inventaris.');
    }

    /**
     * Record an approval decision and advance the flow.
     */
    private function decide(Request $request, Procurement $procurement, ApprovalDecision $decision): RedirectResponse
    {
        abort_unless($procurement->status === ProcurementStatus::Pending, 409, 'Pengajuan sudah diproses.');

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $role = $procurement->currentStepRole();
        $user = $request->user();

        abort_unless($role !== null && ($user->hasRole($role) || $user->hasRole('super-admin')), 403, 'Anda tidak berwenang pada langkah persetujuan ini.');

        DB::transaction(function () use ($procurement, $decision, $validated, $user) {
            $procurement->approvals()->create([
                'step_index' => $procurement->current_step,
                'user_id' => $user->id,
                'decision' => $decision,
                'note' => $validated['note'] ?? null,
            ]);

            if ($decision === ApprovalDecision::Approved) {
                $procurement->current_step++;

                if (! $procurement->hasMoreSteps()) {
                    $procurement->status = ProcurementStatus::Approved;
                }
            } else {
                $procurement->status = ProcurementStatus::Rejected;
            }

            $procurement->save();
        });

        $message = $decision === ApprovalDecision::Approved
            ? 'Persetujuan langkah ini tercatat.'
            : 'Pengajuan ditolak.';

        $procurement->loadMissing('user');

        if ($decision === ApprovalDecision::Rejected) {
            $this->notify($procurement->user, new ProcurementRejected($procurement), $user);
        } elseif ($procurement->status === ProcurementStatus::Approved) {
            $this->notify($procurement->user, new ProcurementApproved($procurement), $user);
        } else {
            $this->notifyApprovers(
                $procurement,
                $procurement->currentStepRole(),
                new ProcurementSubmitted($procurement),
                $user,
            );
        }

        return redirect()->route('procurements.show', $procurement)->with('success', $message);
    }

    /**
     * Notify every approver of the given role except the actor.
     */
    private function notifyApprovers(Procurement $procurement, ?string $role, Notification $notification, ?User $actor = null): void
    {
        if ($role === null) {
            return;
        }

        $this->notifications->sendToRole($role, 'procurement.approve', $notification, $actor);
    }

    /**
     * Send a notification unless the actor is the recipient.
     */
    private function notify(?User $recipient, Notification $notification, ?User $actor = null): void
    {
        if (! $recipient || $actor?->is($recipient)) {
            return;
        }

        $this->notifications->send($recipient, $notification);
    }
}

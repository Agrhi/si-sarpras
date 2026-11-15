<?php

namespace App\Http\Controllers\Sarpras;

use App\Enums\ApprovalDecision;
use App\Enums\AssetStatus;
use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Http\Controllers\Controller;
use App\Models\ApprovalFlow;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\User;
use App\Notifications\Sarpras\DisposalApproved;
use App\Notifications\Sarpras\DisposalCompleted;
use App\Notifications\Sarpras\DisposalRejected;
use App\Notifications\Sarpras\DisposalSubmitted;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisposalController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Display a listing of disposal requests.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search', '');
        $perPage = (int) $request->get('perPage', 10);
        $status = $request->get('status', '');

        $disposals = Disposal::query()
            ->with(['user:id,name', 'asset:id,inventory_code,name'])
            ->when($search, function ($query, $search) {
                $query->where('reason', 'like', "%{$search}%");
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when(! $request->user()->can('disposal.approve'), function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (Disposal $disposal) => [
                'id' => $disposal->id,
                'asset' => $disposal->asset
                    ? "{$disposal->asset->inventory_code} · {$disposal->asset->name}"
                    : '—',
                'proposer' => $disposal->user->name,
                'reason' => $disposal->reason,
                'created_at' => $disposal->created_at->format('d/m/Y'),
                'status' => ['value' => $disposal->status->value, 'label' => $disposal->status->label()],
            ]);

        return Inertia::render('sarpras/disposals/index', [
            'disposals' => $disposals->items(),
            'total' => $disposals->total(),
            'page' => $disposals->currentPage(),
            'perPage' => $disposals->perPage(),
            'search' => $search,
            'status' => $status,
            'statuses' => DisposalStatus::options(),
            'can_create' => $request->user()->can('disposal.create'),
        ]);
    }

    /**
     * Show the form for creating a disposal request.
     */
    public function create(): Response
    {
        return Inertia::render('sarpras/disposals/create', [
            'assets' => Asset::query()
                ->where('status', '!=', AssetStatus::Dihapus->value)
                ->orderBy('name')
                ->get(['id', 'inventory_code', 'name'])
                ->map(fn (Asset $asset) => [
                    'value' => $asset->id,
                    'label' => "{$asset->inventory_code} · {$asset->name}",
                ]),
        ]);
    }

    /**
     * Store a newly submitted disposal request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->whereNot('status', AssetStatus::Dihapus->value),
            ],
            'reason' => ['required', 'string', 'max:5000'],
            'evidence' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);

        $running = Disposal::query()
            ->where('asset_id', $validated['asset_id'])
            ->whereIn('status', [DisposalStatus::Pending, DisposalStatus::Approved])
            ->exists();

        if ($running) {
            return back()->withErrors(['asset_id' => 'Aset ini sudah memiliki usulan penghapusan yang sedang berjalan.']);
        }

        $steps = ApprovalFlow::query()
            ->where('module', 'disposal')
            ->where('is_active', true)
            ->value('steps') ?? [];

        if (empty($steps)) {
            return back()->withErrors(['asset_id' => 'Alur persetujuan penghapusan belum dikonfigurasi.']);
        }

        $disposal = Disposal::create([
            'user_id' => $request->user()->id,
            'asset_id' => $validated['asset_id'],
            'reason' => $validated['reason'],
            'evidence_path' => $request->file('evidence')?->store('disposals/evidence', 'public'),
            'status' => DisposalStatus::Pending,
            'approval_steps' => $steps,
            'current_step' => 0,
        ]);

        $disposal->loadMissing(['user', 'asset']);

        $this->notifyApprovers($disposal, $steps[0]['role'] ?? null, new DisposalSubmitted($disposal), $request->user());

        return redirect()->route('disposals.show', $disposal)->with('success', 'Usulan penghapusan berhasil dikirim.');
    }

    /**
     * Display the specified disposal request.
     */
    public function show(Request $request, Disposal $disposal): Response
    {
        $disposal->load([
            'user:id,name',
            'asset:id,inventory_code,name,status',
            'approvals.user:id,name',
        ]);

        $user = $request->user();
        $pending = $disposal->status === DisposalStatus::Pending;

        $steps = collect($disposal->approval_steps)->map(function (array $step, int $index) use ($disposal, $pending) {
            $approval = $disposal->approvals->firstWhere('step_index', $index);

            return [
                'title' => $step['title'],
                'role' => $step['role'],
                'state' => $disposal->stepState($index, $pending),
                'approval' => $approval ? [
                    'user' => $approval->user->name,
                    'decision' => $approval->decision->label(),
                    'note' => $approval->note,
                    'created_at' => $approval->created_at->format('d/m/Y H:i'),
                ] : null,
            ];
        });

        $canApprove = $pending
            && $user->can('disposal.approve')
            && ($role = $disposal->currentStepRole()) !== null
            && ($user->hasRole($role) || $user->hasRole('super-admin'));

        return Inertia::render('sarpras/disposals/show', [
            'disposal' => [
                'id' => $disposal->id,
                'proposer' => $disposal->user->name,
                'asset' => $disposal->asset ? [
                    'id' => $disposal->asset->id,
                    'inventory_code' => $disposal->asset->inventory_code,
                    'name' => $disposal->asset->name,
                    'status' => $disposal->asset->status->label(),
                ] : null,
                'reason' => $disposal->reason,
                'created_at' => $disposal->created_at->format('d/m/Y H:i'),
                'status' => ['value' => $disposal->status->value, 'label' => $disposal->status->label()],
                'evidence_url' => $disposal->evidence_path
                    ? Storage::disk('public')->url($disposal->evidence_path)
                    : null,
                'method' => $disposal->method?->label(),
                'realized_at' => $disposal->realized_at?->format('d/m/Y'),
                'document_url' => $disposal->document_path
                    ? Storage::disk('public')->url($disposal->document_path)
                    : null,
                'steps' => $steps,
            ],
            'methods' => DisposalMethod::options(),
            'can_approve' => $canApprove,
            'can_complete' => $disposal->status === DisposalStatus::Approved && $user->can('disposal.complete'),
        ]);
    }

    /**
     * Approve the disposal request at the current step.
     */
    public function approve(Request $request, Disposal $disposal): RedirectResponse
    {
        return $this->decide($request, $disposal, ApprovalDecision::Approved);
    }

    /**
     * Reject the disposal request at the current step.
     */
    public function reject(Request $request, Disposal $disposal): RedirectResponse
    {
        return $this->decide($request, $disposal, ApprovalDecision::Rejected);
    }

    /**
     * Record the realisation and mark the asset as disposed.
     */
    public function complete(Request $request, Disposal $disposal): RedirectResponse
    {
        abort_unless($disposal->status === DisposalStatus::Approved, 409, 'Hanya usulan disetujui yang dapat direalisasikan.');

        $disposal->loadMissing('asset');

        abort_if($disposal->asset?->status === AssetStatus::Dipinjam, 409, 'Aset masih dipinjam dan belum dapat dihapus.');

        $validated = $request->validate([
            'method' => ['required', Rule::enum(DisposalMethod::class)],
            'realized_at' => ['required', 'date'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ]);

        $documentPath = $request->file('document')?->store('disposals/documents', 'public');

        DB::transaction(function () use ($disposal, $validated, $documentPath) {
            $disposal->update([
                'status' => DisposalStatus::Completed,
                'method' => $validated['method'],
                'realized_at' => $validated['realized_at'],
                'document_path' => $documentPath,
            ]);

            $disposal->asset?->update(['status' => AssetStatus::Dihapus]);
        });

        $disposal->loadMissing(['user', 'asset']);
        $this->notify($disposal->user, new DisposalCompleted($disposal), $request->user());

        return redirect()->route('disposals.show', $disposal)
            ->with('success', 'Penghapusan tercatat. Aset kini berstatus dihapus.');
    }

    /**
     * Record an approval decision and advance the flow.
     */
    private function decide(Request $request, Disposal $disposal, ApprovalDecision $decision): RedirectResponse
    {
        abort_unless($disposal->status === DisposalStatus::Pending, 409, 'Pengajuan sudah diproses.');

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $role = $disposal->currentStepRole();
        $user = $request->user();

        abort_unless($role !== null && ($user->hasRole($role) || $user->hasRole('super-admin')), 403, 'Anda tidak berwenang pada langkah persetujuan ini.');

        DB::transaction(function () use ($disposal, $decision, $validated, $user) {
            $disposal->approvals()->create([
                'step_index' => $disposal->current_step,
                'user_id' => $user->id,
                'decision' => $decision,
                'note' => $validated['note'] ?? null,
            ]);

            if ($decision === ApprovalDecision::Approved) {
                $disposal->current_step++;

                if (! $disposal->hasMoreSteps()) {
                    $disposal->status = DisposalStatus::Approved;
                }
            } else {
                $disposal->status = DisposalStatus::Rejected;
            }

            $disposal->save();
        });

        $message = $decision === ApprovalDecision::Approved
            ? 'Persetujuan langkah ini tercatat.'
            : 'Pengajuan ditolak.';

        $disposal->loadMissing(['user', 'asset']);

        if ($decision === ApprovalDecision::Rejected) {
            $this->notify($disposal->user, new DisposalRejected($disposal), $user);
        } elseif ($disposal->status === DisposalStatus::Approved) {
            $this->notify($disposal->user, new DisposalApproved($disposal), $user);
        } else {
            $this->notifyApprovers(
                $disposal,
                $disposal->currentStepRole(),
                new DisposalSubmitted($disposal),
                $user,
            );
        }

        return redirect()->route('disposals.show', $disposal)->with('success', $message);
    }

    /**
     * Notify every approver of the given role except the actor.
     */
    private function notifyApprovers(Disposal $disposal, ?string $role, Notification $notification, ?User $actor = null): void
    {
        if ($role === null) {
            return;
        }

        $this->notifications->sendToRole($role, 'disposal.approve', $notification, $actor);
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

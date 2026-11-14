<?php

namespace App\Http\Controllers\Sarpras;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceUrgency;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\MaintenanceReport;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceReportController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    /**
     * Display a listing of damage reports.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search', '');
        $perPage = (int) $request->get('perPage', 10);
        $status = $request->get('status', '');
        $urgency = $request->get('urgency', '');
        $mine = $request->boolean('mine');

        $reports = MaintenanceReport::query()
            ->with(['asset:id,inventory_code,name', 'reporter:id,name', 'assignee:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('asset', fn ($asset) => $asset->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->when($urgency, fn ($query, $urgency) => $query->where('urgency', $urgency))
            ->when($mine, fn ($query) => $query->where('assigned_to', $request->user()->id))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->through(fn (MaintenanceReport $report) => [
                'id' => $report->id,
                'asset' => $report->asset
                    ? "{$report->asset->inventory_code} · {$report->asset->name}"
                    : '—',
                'reporter' => $report->reporter?->name,
                'assignee' => $report->assignee?->name,
                'urgency' => ['value' => $report->urgency->value, 'label' => $report->urgency->label()],
                'status' => ['value' => $report->status->value, 'label' => $report->status->label()],
                'created_at' => $report->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('sarpras/maintenance-reports/index', [
            'reports' => $reports->items(),
            'total' => $reports->total(),
            'page' => $reports->currentPage(),
            'perPage' => $reports->perPage(),
            'search' => $search,
            'status' => $status,
            'urgency' => $urgency,
            'mine' => $mine,
            'statuses' => MaintenanceStatus::options(),
            'urgencies' => MaintenanceUrgency::options(),
            'can_create' => $request->user()->can('maintenance-report.create'),
            'can_process' => $request->user()->can('maintenance-report.process'),
        ]);
    }

    /**
     * Show the form for creating a damage report.
     */
    public function create(): Response
    {
        return Inertia::render('sarpras/maintenance-reports/create', [
            'assets' => Asset::query()
                ->where('status', '!=', AssetStatus::Dihapus->value)
                ->orderBy('name')
                ->get(['id', 'inventory_code', 'name', 'status'])
                ->map(fn (Asset $asset) => [
                    'value' => $asset->id,
                    'label' => "{$asset->inventory_code} · {$asset->name}",
                ]),
            'urgencies' => MaintenanceUrgency::options(),
        ]);
    }

    /**
     * Store a newly submitted damage report.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->whereNot('status', AssetStatus::Dihapus->value),
            ],
            'urgency' => ['required', Rule::enum(MaintenanceUrgency::class)],
            'description' => ['required', 'string', 'max:5000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $photo = $request->file('photo');

        if ($photo && ! $photo->isValid()) {
            throw ValidationException::withMessages([
                'photo' => 'Foto gagal diunggah. Perkecil ukuran foto atau pilih file lain.',
            ]);
        }

        $photoPath = $photo?->store('maintenance-reports', 'public');

        $report = $this->maintenance->createReport([
            'asset_id' => $validated['asset_id'],
            'urgency' => $validated['urgency'],
            'description' => $validated['description'],
            'photo_path' => $photoPath,
        ], $request->user());

        return redirect()
            ->route('maintenance-reports.show', $report)
            ->with('success', 'Laporan kerusakan berhasil dikirim.');
    }

    /**
     * Display the specified damage report.
     */
    public function show(Request $request, MaintenanceReport $maintenanceReport): Response
    {
        $maintenanceReport->load([
            'asset:id,inventory_code,name,status,condition',
            'reporter:id,name',
            'assignee:id,name',
        ]);

        $user = $request->user();
        $isOpen = $maintenanceReport->status->isOpen();
        $canCancel = $user->can('maintenance-report.cancel')
            && $isOpen
            && ($user->can('maintenance-report.assign') || $maintenanceReport->reported_by === $user->id);

        return Inertia::render('sarpras/maintenance-reports/show', [
            'report' => [
                'id' => $maintenanceReport->id,
                'asset' => $maintenanceReport->asset ? [
                    'id' => $maintenanceReport->asset->id,
                    'inventory_code' => $maintenanceReport->asset->inventory_code,
                    'name' => $maintenanceReport->asset->name,
                    'status' => $maintenanceReport->asset->status->label(),
                ] : null,
                'reporter' => $maintenanceReport->reporter?->name,
                'assignee' => $maintenanceReport->assignee?->name,
                'urgency' => [
                    'value' => $maintenanceReport->urgency->value,
                    'label' => $maintenanceReport->urgency->label(),
                ],
                'status' => [
                    'value' => $maintenanceReport->status->value,
                    'label' => $maintenanceReport->status->label(),
                ],
                'description' => $maintenanceReport->description,
                'photo_url' => $maintenanceReport->photo_path
                    ? Storage::disk('public')->url($maintenanceReport->photo_path)
                    : null,
                'action_taken' => $maintenanceReport->action_taken,
                'cost' => $maintenanceReport->cost,
                'spare_parts' => $maintenanceReport->spare_parts,
                'started_at' => $maintenanceReport->started_at?->format('d/m/Y H:i'),
                'completed_at' => $maintenanceReport->completed_at?->format('d/m/Y H:i'),
                'created_at' => $maintenanceReport->created_at->format('d/m/Y H:i'),
            ],
            'technicians' => $this->maintenance->technicians()
                ->map(fn (User $technician) => ['value' => $technician->id, 'label' => $technician->name]),
            'can' => [
                'assign' => $user->can('maintenance-report.assign') && $isOpen,
                'process' => $user->can('maintenance-report.process') && $isOpen,
                'cancel' => $canCancel,
            ],
        ]);
    }

    /**
     * Assign a technician to the report.
     */
    public function assign(Request $request, MaintenanceReport $maintenanceReport): RedirectResponse
    {
        abort_unless($maintenanceReport->status->isOpen(), 409, 'Laporan sudah ditutup.');

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $technician = User::query()->findOrFail($validated['assigned_to']);

        abort_unless(
            $technician->hasAnyRole(['petugas-teknis', 'admin', 'super-admin']),
            422,
            'Pengguna yang dipilih bukan petugas teknis.',
        );

        $this->maintenance->assign($maintenanceReport, $technician, $request->user());

        return back()->with('success', 'Petugas teknis berhasil ditugaskan.');
    }

    /**
     * Mark the report as being processed.
     */
    public function start(Request $request, MaintenanceReport $maintenanceReport): RedirectResponse
    {
        abort_unless($maintenanceReport->status === MaintenanceStatus::Pending, 409, 'Laporan tidak dapat diproses.');

        $this->maintenance->start($maintenanceReport, $request->user());

        return back()->with('success', 'Laporan mulai diproses.');
    }

    /**
     * Complete the report with the repair details.
     */
    public function complete(Request $request, MaintenanceReport $maintenanceReport): RedirectResponse
    {
        abort_unless($maintenanceReport->status->isOpen(), 409, 'Laporan sudah ditutup.');

        $validated = $request->validate([
            'action_taken' => ['required', 'string', 'max:5000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'spare_parts' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->maintenance->complete($maintenanceReport, $validated, $request->user());

        return back()->with('success', 'Laporan kerusakan diselesaikan.');
    }

    /**
     * Cancel the report.
     */
    public function cancel(Request $request, MaintenanceReport $maintenanceReport): RedirectResponse
    {
        abort_unless($maintenanceReport->status->isOpen(), 409, 'Laporan sudah ditutup.');

        $user = $request->user();
        $isManager = $user->can('maintenance-report.assign');

        if (! $isManager) {
            abort_unless($maintenanceReport->reported_by === $user->id, 403);
            abort_unless($maintenanceReport->status === MaintenanceStatus::Pending, 403, 'Laporan yang sudah diproses tidak dapat dibatalkan.');
        }

        $this->maintenance->cancel($maintenanceReport, $user);

        return back()->with('success', 'Laporan kerusakan dibatalkan.');
    }
}

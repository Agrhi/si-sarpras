<?php

namespace App\Http\Controllers\Sarpras;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceFrequency;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceScheduleController extends Controller
{
    public function __construct(private MaintenanceService $maintenance) {}

    /**
     * Display a listing of maintenance schedules.
     */
    public function index(Request $request): Response
    {
        $search = $request->get('search', '');
        $perPage = (int) $request->get('perPage', 10);
        $state = $request->get('state', '');

        $schedules = MaintenanceSchedule::query()
            ->with(['asset:id,inventory_code,name', 'assignee:id,name'])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('asset', fn ($asset) => $asset->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($state, fn (Builder $query, string $state) => $this->filterByState($query, $state))
            ->orderBy('next_due_at')
            ->paginate($perPage)
            ->through(fn (MaintenanceSchedule $schedule) => [
                'id' => $schedule->id,
                'asset' => $schedule->asset
                    ? "{$schedule->asset->inventory_code} · {$schedule->asset->name}"
                    : '—',
                'title' => $schedule->title,
                'frequency' => [
                    'value' => $schedule->frequency->value,
                    'label' => $schedule->frequency->label(),
                ],
                'assignee' => $schedule->assignee?->name,
                'next_due_at' => $schedule->next_due_at->format('d/m/Y'),
                'last_performed_at' => $schedule->last_performed_at?->format('d/m/Y'),
                'due_state' => $schedule->due_state,
                'is_active' => $schedule->is_active,
            ]);

        return Inertia::render('sarpras/maintenance-schedules/index', [
            'schedules' => $schedules->items(),
            'total' => $schedules->total(),
            'page' => $schedules->currentPage(),
            'perPage' => $schedules->perPage(),
            'search' => $search,
            'state' => $state,
            'states' => [
                ['value' => 'terjadwal', 'label' => 'Terjadwal'],
                ['value' => 'jatuh-tempo', 'label' => 'Jatuh Tempo'],
                ['value' => 'terlambat', 'label' => 'Terlambat'],
                ['value' => 'nonaktif', 'label' => 'Nonaktif'],
            ],
            'can_create' => $request->user()->can('maintenance-schedule.create'),
            'can_edit' => $request->user()->can('maintenance-schedule.edit'),
            'can_delete' => $request->user()->can('maintenance-schedule.delete'),
            'can_execute' => $request->user()->can('maintenance-schedule.execute'),
        ]);
    }

    /**
     * Show the form for creating a maintenance schedule.
     */
    public function create(): Response
    {
        return Inertia::render('sarpras/maintenance-schedules/create', $this->formOptions());
    }

    /**
     * Store a newly created maintenance schedule.
     */
    public function store(Request $request): RedirectResponse
    {
        MaintenanceSchedule::create($this->validated($request));

        return redirect()
            ->route('maintenance-schedules.index')
            ->with('success', 'Jadwal pemeliharaan berhasil dibuat.');
    }

    /**
     * Show the form for editing a maintenance schedule.
     */
    public function edit(MaintenanceSchedule $maintenanceSchedule): Response
    {
        return Inertia::render('sarpras/maintenance-schedules/edit', [
            'schedule' => [
                'id' => $maintenanceSchedule->id,
                'asset_id' => $maintenanceSchedule->asset_id,
                'assigned_to' => $maintenanceSchedule->assigned_to,
                'title' => $maintenanceSchedule->title,
                'description' => $maintenanceSchedule->description,
                'frequency' => $maintenanceSchedule->frequency->value,
                'next_due_at' => $maintenanceSchedule->next_due_at->format('Y-m-d'),
                'is_active' => $maintenanceSchedule->is_active,
            ],
            ...$this->formOptions(),
        ]);
    }

    /**
     * Update the maintenance schedule.
     */
    public function update(Request $request, MaintenanceSchedule $maintenanceSchedule): RedirectResponse
    {
        $maintenanceSchedule->update($this->validated($request));

        return redirect()
            ->route('maintenance-schedules.index')
            ->with('success', 'Jadwal pemeliharaan berhasil diperbarui.');
    }

    /**
     * Remove or deactivate the maintenance schedule.
     */
    public function destroy(MaintenanceSchedule $maintenanceSchedule): RedirectResponse
    {
        if ($maintenanceSchedule->last_performed_at !== null) {
            $maintenanceSchedule->update(['is_active' => false]);

            return redirect()
                ->route('maintenance-schedules.index')
                ->with('success', 'Jadwal yang sudah memiliki realisasi dinonaktifkan.');
        }

        $maintenanceSchedule->delete();

        return redirect()
            ->route('maintenance-schedules.index')
            ->with('success', 'Jadwal pemeliharaan berhasil dihapus.');
    }

    /**
     * Record a maintenance realization.
     */
    public function complete(Request $request, MaintenanceSchedule $maintenanceSchedule): RedirectResponse
    {
        $validated = $request->validate([
            'performed_at' => ['nullable', 'date'],
        ]);

        $this->maintenance->recordPerformance($maintenanceSchedule, $validated);

        return back()->with('success', 'Realisasi pemeliharaan tercatat. Jadwal berikutnya telah diperbarui.');
    }

    /**
     * Validation rules for storing and updating a schedule.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->whereNot('status', AssetStatus::Dihapus->value),
            ],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'frequency' => ['required', Rule::enum(MaintenanceFrequency::class)],
            'next_due_at' => ['required', 'date'],
            'is_active' => ['boolean'],
        ]);
    }

    /**
     * Apply the state filter to the query.
     *
     * @param  Builder<MaintenanceSchedule>  $query
     */
    private function filterByState(Builder $query, string $state): void
    {
        $today = today();
        $window = $today->addDays((int) config('sarpras.maintenance_reminder_days', 3));

        match ($state) {
            'terlambat' => $query->active()->whereDate('next_due_at', '<', $today),
            'jatuh-tempo' => $query->active()
                ->whereDate('next_due_at', '>=', $today)
                ->whereDate('next_due_at', '<=', $window),
            'terjadwal' => $query->active()->whereDate('next_due_at', '>', $window),
            'nonaktif' => $query->where('is_active', false),
            default => null,
        };
    }

    /**
     * Shared select options for the schedule form pages.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'assets' => Asset::query()
                ->orderBy('name')
                ->get(['id', 'inventory_code', 'name'])
                ->map(fn (Asset $asset) => [
                    'value' => $asset->id,
                    'label' => "{$asset->inventory_code} · {$asset->name}",
                ]),
            'technicians' => $this->maintenance->technicians()
                ->map(fn (User $technician) => [
                    'value' => $technician->id,
                    'label' => $technician->name,
                ]),
            'frequencies' => MaintenanceFrequency::options(),
        ];
    }
}

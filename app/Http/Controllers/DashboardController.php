<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DisposalStatus;
use App\Enums\LoanStatus;
use App\Enums\ProcurementStatus;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\LoanRequest;
use App\Models\MaintenanceReport;
use App\Models\MaintenanceSchedule;
use App\Models\Procurement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    /**
     * Show the dashboard with stats scoped to the user's access.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $isAdmin = $user->can('admin.access');

        return Inertia::render('dashboard', [
            'sarpras' => $this->sarprasSummary($user),
            'globalStats' => $isAdmin ? [
                'users' => User::count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'teams' => Team::count(),
            ] : null,
            'personal' => [
                'teams' => $user->teams()->count(),
                'roles' => $user->getRoleNames(),
                'unreadNotifications' => $user->unreadNotifications()->count(),
                'memberSince' => $user->created_at->toISOString(),
            ],
            'recentUsers' => $isAdmin
                ? User::with('roles')->latest()->take(5)->get()->map(fn (User $recent) => [
                    'id' => $recent->id,
                    'name' => $recent->name,
                    'email' => $recent->email,
                    'roles' => $recent->getRoleNames(),
                    'created_at' => $recent->created_at->toISOString(),
                ])
                : [],
        ]);
    }

    /**
     * Summarise the sarpras modules the user is allowed to see.
     *
     * @return array<string, mixed>|null
     */
    private function sarprasSummary(User $user): ?array
    {
        $summary = [];

        if ($user->can('asset.view')) {
            $statusCounts = Asset::query()
                ->select('status')->selectRaw('count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
            $conditionCounts = Asset::query()
                ->select('condition')->selectRaw('count(*) as aggregate')
                ->groupBy('condition')
                ->pluck('aggregate', 'condition');

            $summary['assets'] = [
                'total' => Asset::count(),
                'byStatus' => collect(AssetStatus::cases())
                    ->map(fn (AssetStatus $status) => [
                        'value' => $status->value,
                        'label' => $status->label(),
                        'count' => (int) ($statusCounts[$status->value] ?? 0),
                    ])
                    ->all(),
                'byCondition' => collect(AssetCondition::cases())
                    ->map(fn (AssetCondition $condition) => [
                        'value' => $condition->value,
                        'label' => $condition->label(),
                        'count' => (int) ($conditionCounts[$condition->value] ?? 0),
                    ])
                    ->all(),
                'byCategory' => Asset::query()
                    ->select('category_id')->selectRaw('count(*) as aggregate')
                    ->groupBy('category_id')
                    ->orderByDesc('aggregate')
                    ->limit(5)
                    ->with('category:id,name')
                    ->get()
                    ->map(fn (Asset $asset) => [
                        'label' => $asset->category?->name ?? 'Tanpa kategori',
                        'count' => (int) $asset->aggregate,
                    ])
                    ->all(),
                'byLocation' => Asset::query()
                    ->select('location_id')->selectRaw('count(*) as aggregate')
                    ->groupBy('location_id')
                    ->orderByDesc('aggregate')
                    ->limit(5)
                    ->with('location:id,name')
                    ->get()
                    ->map(fn (Asset $asset) => [
                        'label' => $asset->location?->name ?? 'Tanpa lokasi',
                        'count' => (int) $asset->aggregate,
                    ])
                    ->all(),
            ];
        }

        if ($user->can('loan.view')) {
            $activeLoans = LoanRequest::query()
                ->where('status', LoanStatus::Active->value)
                ->with('user:id,name')
                ->orderBy('due_at');
            $reminderWindow = now()->addDays((int) config('sarpras.loan_reminder_days', 1))->endOfDay();

            $summary['loans'] = [
                'active' => (clone $activeLoans)->count(),
                'dueSoon' => (clone $activeLoans)->whereBetween('due_at', [now(), $reminderWindow])->count(),
                'overdue' => (clone $activeLoans)->where('due_at', '<', now())->count(),
                'items' => $activeLoans->limit(5)->get()->map(fn (LoanRequest $loan) => [
                    'id' => $loan->id,
                    'borrower' => $loan->user?->name ?? '—',
                    'due_at' => $loan->due_at->format('d/m/Y H:i'),
                    'overdue_days' => $loan->due_at->isPast()
                        ? (int) floor((now()->getTimestamp() - $loan->due_at->getTimestamp()) / 86400)
                        : 0,
                ])->all(),
            ];
        }

        if ($user->can('maintenance-report.view')) {
            $summary['maintenance'] = [
                'open' => MaintenanceReport::query()->open()->count(),
                'items' => MaintenanceReport::query()
                    ->open()
                    ->with('asset:id,inventory_code,name')
                    ->oldest('created_at')
                    ->limit(5)
                    ->get()
                    ->map(fn (MaintenanceReport $report) => [
                        'id' => $report->id,
                        'asset' => $report->asset
                            ? "{$report->asset->inventory_code} — {$report->asset->name}"
                            : 'Tanpa aset',
                        'urgency' => ['value' => $report->urgency->value, 'label' => $report->urgency->label()],
                        'status' => $report->status->label(),
                        'created_at' => $report->created_at->format('d/m/Y'),
                    ])
                    ->all(),
            ];
        }

        if ($user->can('maintenance-schedule.view')) {
            $window = today()->addDays((int) config('sarpras.maintenance_reminder_days', 3));
            $dueSchedules = MaintenanceSchedule::query()
                ->active()
                ->whereDate('next_due_at', '<=', $window)
                ->with('asset:id,inventory_code,name')
                ->orderBy('next_due_at');

            $summary['schedules'] = [
                'due' => (clone $dueSchedules)->count(),
                'items' => $dueSchedules->limit(5)->get()->map(fn (MaintenanceSchedule $schedule) => [
                    'id' => $schedule->id,
                    'asset' => $schedule->asset
                        ? "{$schedule->asset->inventory_code} — {$schedule->asset->name}"
                        : 'Tanpa aset',
                    'title' => $schedule->title,
                    'next_due_at' => $schedule->next_due_at->format('d/m/Y'),
                    'state' => $schedule->due_state,
                ])->all(),
            ];
        }

        if ($user->can('procurement.view')) {
            $counts = Procurement::query()
                ->select('status')->selectRaw('count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $summary['procurements'] = [
                'pending' => (int) ($counts[ProcurementStatus::Pending->value] ?? 0),
                'approved' => (int) ($counts[ProcurementStatus::Approved->value] ?? 0),
                'received' => (int) ($counts[ProcurementStatus::Received->value] ?? 0),
            ];
        }

        if ($user->can('disposal.view')) {
            $counts = Disposal::query()
                ->select('status')->selectRaw('count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $summary['disposals'] = [
                'pending' => (int) ($counts[DisposalStatus::Pending->value] ?? 0),
                'approved' => (int) ($counts[DisposalStatus::Approved->value] ?? 0),
                'completed' => (int) ($counts[DisposalStatus::Completed->value] ?? 0),
            ];
        }

        return $summary === [] ? null : $summary;
    }
}

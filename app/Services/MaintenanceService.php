<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\MaintenanceReport;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Notifications\Sarpras\MaintenanceReportAssigned;
use App\Notifications\Sarpras\MaintenanceReportStatusChanged;
use App\Notifications\Sarpras\MaintenanceReportSubmitted;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Submit a new damage report and flag the asset as under repair.
     *
     * @param  array<string, mixed>  $data
     */
    public function createReport(array $data, User $reporter): MaintenanceReport
    {
        $report = DB::transaction(function () use ($data, $reporter) {
            $report = MaintenanceReport::create([
                ...$data,
                'reported_by' => $reporter->id,
                'status' => MaintenanceStatus::Pending,
            ]);

            $report->asset->loadMissing('maintenanceReports');

            $this->markAssetUnderRepair($report->asset);

            return $report;
        });

        $report->loadMissing('asset');

        $this->notifyTechnicians(new MaintenanceReportSubmitted($report), $reporter);

        return $report;
    }

    /**
     * Assign a technician to the report.
     */
    public function assign(MaintenanceReport $report, User $technician, ?User $actor = null): void
    {
        $report->update(['assigned_to' => $technician->id]);
        $report->loadMissing('asset');

        if ($actor?->is($technician)) {
            return;
        }

        $this->notifications->send($technician, new MaintenanceReportAssigned($report));
    }

    /**
     * Move the report into the "in progress" state.
     */
    public function start(MaintenanceReport $report, ?User $actor = null): void
    {
        $report->update([
            'status' => MaintenanceStatus::InProgress,
            'started_at' => now(),
            'assigned_to' => $report->assigned_to ?? $actor?->id,
        ]);

        $report->loadMissing('asset');

        $this->notifyReporter($report, $actor);
    }

    /**
     * Complete the report and record the action, cost, and spare parts.
     *
     * @param  array<string, mixed>  $data
     */
    public function complete(MaintenanceReport $report, array $data, ?User $actor = null): void
    {
        DB::transaction(function () use ($report, $data) {
            $report->update([
                'status' => MaintenanceStatus::Completed,
                'action_taken' => $data['action_taken'],
                'cost' => $data['cost'] ?? null,
                'spare_parts' => $data['spare_parts'] ?? null,
                'completed_at' => now(),
            ]);

            $this->restoreAssetIfFree($report->asset);
        });

        $report->loadMissing('asset');

        $this->notifyReporter($report, $actor);
    }

    /**
     * Cancel the report.
     */
    public function cancel(MaintenanceReport $report, ?User $actor = null): void
    {
        DB::transaction(function () use ($report) {
            $report->update(['status' => MaintenanceStatus::Cancelled]);

            $this->restoreAssetIfFree($report->asset);
        });

        $report->loadMissing('asset');

        $this->notifyReporter($report, $actor);
    }

    /**
     * Record a maintenance schedule realization and compute the next due date.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordPerformance(MaintenanceSchedule $schedule, array $data): void
    {
        $performedOn = isset($data['performed_at'])
            ? CarbonImmutable::parse($data['performed_at'])
            : CarbonImmutable::today();

        $schedule->update([
            'last_performed_at' => $performedOn->toDateString(),
            'next_due_at' => $schedule->frequency->nextDueFrom($performedOn)->toDateString(),
            'last_reminded_on' => null,
        ]);
    }

    /**
     * Flag the asset as under repair when it is currently available.
     */
    public function markAssetUnderRepair(Asset $asset): void
    {
        if ($asset->status === AssetStatus::Aktif) {
            $asset->update(['status' => AssetStatus::DalamPerbaikan]);
        }
    }

    /**
     * Return the asset to service when no open reports remain.
     */
    public function restoreAssetIfFree(Asset $asset): void
    {
        if ($asset->status !== AssetStatus::DalamPerbaikan) {
            return;
        }

        if ($asset->maintenanceReports()->open()->exists()) {
            return;
        }

        $asset->update(['status' => AssetStatus::Aktif]);
    }

    /**
     * Ensure the asset status reflects any open damage reports.
     */
    public function syncAssetStatus(Asset $asset): void
    {
        if ($asset->maintenanceReports()->open()->exists()) {
            $asset->update(['status' => AssetStatus::DalamPerbaikan]);

            return;
        }

        if ($asset->status === AssetStatus::DalamPerbaikan) {
            $asset->update(['status' => AssetStatus::Aktif]);
        }
    }

    /**
     * Send the notification to every technician, falling back to the
     * administrators when no technician exists yet.
     */
    private function notifyTechnicians(Notification $notification, ?User $actor = null): void
    {
        $recipients = $this->technicians();

        if ($recipients->isEmpty()) {
            $recipients = $this->administrators();
        }

        $recipients = $recipients->reject(fn (User $user) => $actor?->is($user));

        if ($recipients->isNotEmpty()) {
            $this->notifications->sendToMultiple($recipients, $notification);
        }
    }

    /**
     * Sarpras administrators, used as the fallback recipient.
     *
     * @return Collection<int, User>
     */
    private function administrators(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super-admin']))
            ->orderBy('name')
            ->get();
    }

    /**
     * Send the notification to the reporter except when they are the actor.
     */
    private function notifyReporter(MaintenanceReport $report, ?User $actor = null): void
    {
        $reporter = $report->reporter;

        if (! $reporter || $actor?->is($reporter)) {
            return;
        }

        $this->notifications->send($reporter, new MaintenanceReportStatusChanged($report));
    }

    /**
     * Get all users holding the technician role.
     *
     * @return Collection<int, User>
     */
    public function technicians(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', 'petugas-teknis'))
            ->orderBy('name')
            ->get();
    }
}

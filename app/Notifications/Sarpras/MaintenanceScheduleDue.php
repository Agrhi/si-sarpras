<?php

namespace App\Notifications\Sarpras;

use App\Models\MaintenanceSchedule;

class MaintenanceScheduleDue extends SarprasNotification
{
    public function __construct(MaintenanceSchedule $schedule)
    {
        $asset = $schedule->asset;

        parent::__construct(
            message: "Jadwal pemeliharaan \"{$schedule->title}\" untuk {$asset->name} jatuh tempo pada {$schedule->next_due_at->format('d/m/Y')}.",
            url: route('maintenance-schedules.index'),
            type: 'maintenance-schedule-due',
            context: ['maintenance_schedule_id' => $schedule->id],
        );
    }
}

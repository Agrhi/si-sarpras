<?php

namespace App\Notifications\Sarpras;

use App\Models\MaintenanceReport;

class MaintenanceReportAssigned extends SarprasNotification
{
    public function __construct(MaintenanceReport $report)
    {
        $asset = $report->asset;

        parent::__construct(
            message: "Anda ditugaskan menangani laporan kerusakan {$asset->name}.",
            url: route('maintenance-reports.show', $report),
            type: 'maintenance-report-assigned',
            context: ['maintenance_report_id' => $report->id],
        );
    }
}

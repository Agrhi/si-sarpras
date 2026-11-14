<?php

namespace App\Notifications\Sarpras;

use App\Models\MaintenanceReport;

class MaintenanceReportStatusChanged extends SarprasNotification
{
    public function __construct(MaintenanceReport $report)
    {
        $asset = $report->asset;

        parent::__construct(
            message: "Status laporan kerusakan {$asset->name} kini {$report->status->label()}.",
            url: route('maintenance-reports.show', $report),
            type: 'maintenance-report-status-changed',
            context: ['maintenance_report_id' => $report->id],
        );
    }
}

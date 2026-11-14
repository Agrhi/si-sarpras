<?php

namespace App\Notifications\Sarpras;

use App\Models\MaintenanceReport;

class MaintenanceReportSubmitted extends SarprasNotification
{
    public function __construct(MaintenanceReport $report)
    {
        $asset = $report->asset;

        parent::__construct(
            message: "Laporan kerusakan baru untuk {$asset->name} dengan urgensi {$report->urgency->label()}.",
            url: route('maintenance-reports.show', $report),
            type: 'maintenance-report-submitted',
            context: ['maintenance_report_id' => $report->id],
        );
    }
}

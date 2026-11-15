<?php

namespace App\Notifications\Sarpras;

use App\Models\Procurement;

class ProcurementApproved extends SarprasNotification
{
    public function __construct(Procurement $procurement)
    {
        parent::__construct(
            message: "Pengajuan pengadaan \"{$procurement->title}\" telah disetujui.",
            url: route('procurements.show', $procurement),
            type: 'procurement-approved',
            context: ['procurement_id' => $procurement->id],
        );
    }
}

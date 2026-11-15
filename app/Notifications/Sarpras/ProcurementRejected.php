<?php

namespace App\Notifications\Sarpras;

use App\Models\Procurement;

class ProcurementRejected extends SarprasNotification
{
    public function __construct(Procurement $procurement)
    {
        parent::__construct(
            message: "Pengajuan pengadaan \"{$procurement->title}\" ditolak.",
            url: route('procurements.show', $procurement),
            type: 'procurement-rejected',
            context: ['procurement_id' => $procurement->id],
        );
    }
}

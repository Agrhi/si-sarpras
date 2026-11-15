<?php

namespace App\Notifications\Sarpras;

use App\Models\Procurement;

class ProcurementSubmitted extends SarprasNotification
{
    public function __construct(Procurement $procurement)
    {
        parent::__construct(
            message: "Pengajuan pengadaan \"{$procurement->title}\" menunggu persetujuan Anda.",
            url: route('procurements.show', $procurement),
            type: 'procurement-submitted',
            context: ['procurement_id' => $procurement->id],
        );
    }
}

<?php

namespace App\Notifications\Sarpras;

use App\Models\Procurement;

class ProcurementReceived extends SarprasNotification
{
    public function __construct(Procurement $procurement)
    {
        parent::__construct(
            message: "Barang dari pengadaan \"{$procurement->title}\" telah diterima dan tercatat sebagai aset.",
            url: route('procurements.show', $procurement),
            type: 'procurement-received',
            context: ['procurement_id' => $procurement->id],
        );
    }
}

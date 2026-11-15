<?php

namespace App\Notifications\Sarpras;

use App\Models\Disposal;

class DisposalApproved extends SarprasNotification
{
    public function __construct(Disposal $disposal)
    {
        parent::__construct(
            message: "Usulan penghapusan aset \"{$disposal->asset->name}\" telah disetujui.",
            url: route('disposals.show', $disposal),
            type: 'disposal-approved',
            context: ['disposal_id' => $disposal->id],
        );
    }
}

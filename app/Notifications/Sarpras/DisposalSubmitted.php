<?php

namespace App\Notifications\Sarpras;

use App\Models\Disposal;

class DisposalSubmitted extends SarprasNotification
{
    public function __construct(Disposal $disposal)
    {
        parent::__construct(
            message: "Usulan penghapusan aset \"{$disposal->asset->name}\" menunggu persetujuan Anda.",
            url: route('disposals.show', $disposal),
            type: 'disposal-submitted',
            context: ['disposal_id' => $disposal->id],
        );
    }
}

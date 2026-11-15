<?php

namespace App\Notifications\Sarpras;

use App\Models\Disposal;

class DisposalRejected extends SarprasNotification
{
    public function __construct(Disposal $disposal)
    {
        parent::__construct(
            message: "Usulan penghapusan aset \"{$disposal->asset->name}\" ditolak.",
            url: route('disposals.show', $disposal),
            type: 'disposal-rejected',
            context: ['disposal_id' => $disposal->id],
        );
    }
}

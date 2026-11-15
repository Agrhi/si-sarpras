<?php

namespace App\Notifications\Sarpras;

use App\Models\Disposal;

class DisposalCompleted extends SarprasNotification
{
    public function __construct(Disposal $disposal)
    {
        parent::__construct(
            message: "Penghapusan aset \"{$disposal->asset->name}\" telah direalisasikan.",
            url: route('disposals.show', $disposal),
            type: 'disposal-completed',
            context: ['disposal_id' => $disposal->id],
        );
    }
}

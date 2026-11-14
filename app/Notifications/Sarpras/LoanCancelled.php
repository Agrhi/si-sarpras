<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanCancelled extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: "Pengajuan peminjaman \"{$loan->purpose}\" dibatalkan oleh pemohon.",
            url: route('loans.show', $loan),
            type: 'loan-cancelled',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

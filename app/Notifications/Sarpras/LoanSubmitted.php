<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanSubmitted extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: "Pengajuan peminjaman baru dari {$loan->user->name} menunggu persetujuan Anda.",
            url: route('loans.show', $loan),
            type: 'loan-submitted',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

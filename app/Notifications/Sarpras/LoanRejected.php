<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanRejected extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: 'Pengajuan peminjaman Anda ditolak.',
            url: route('loans.show', $loan),
            type: 'loan-rejected',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

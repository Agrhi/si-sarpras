<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanReturned extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: 'Pengembalian peminjaman Anda telah dicatat. Terima kasih.',
            url: route('loans.show', $loan),
            type: 'loan-returned',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanDueSoon extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: "Peminjaman Anda akan jatuh tempo pada {$loan->due_at->format('d/m/Y H:i')}.",
            url: route('loans.show', $loan),
            type: 'loan-due-soon',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

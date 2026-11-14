<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanOverdue extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: "Peminjaman Anda telah melewati tenggat ({$loan->due_at->format('d/m/Y H:i')}). Segera kembalikan barang.",
            url: route('loans.show', $loan),
            type: 'loan-overdue',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

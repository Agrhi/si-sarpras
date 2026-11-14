<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanHandedOver extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: 'Barang peminjaman Anda telah diserahkan. Mohon kembalikan sebelum jatuh tempo.',
            url: route('loans.show', $loan),
            type: 'loan-handed-over',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

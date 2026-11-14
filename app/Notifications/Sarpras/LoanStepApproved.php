<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanStepApproved extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: "Pengajuan peminjaman dari {$loan->user->name} menunggu persetujuan Anda.",
            url: route('loans.show', $loan),
            type: 'loan-step-approved',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

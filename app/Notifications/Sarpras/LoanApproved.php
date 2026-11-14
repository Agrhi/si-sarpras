<?php

namespace App\Notifications\Sarpras;

use App\Models\LoanRequest;

class LoanApproved extends SarprasNotification
{
    public function __construct(LoanRequest $loan)
    {
        parent::__construct(
            message: 'Pengajuan peminjaman Anda telah disetujui. Silakan ambil barang pada waktu yang ditentukan.',
            url: route('loans.show', $loan),
            type: 'loan-approved',
            context: ['loan_request_id' => $loan->id],
        );
    }
}

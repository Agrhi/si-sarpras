<?php

use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Models\ApprovalFlow;
use App\Models\Asset;
use App\Models\LoanRequest;
use App\Models\MaintenanceReport;
use App\Notifications\Sarpras\LoanApproved;
use App\Notifications\Sarpras\LoanCancelled;
use App\Notifications\Sarpras\LoanHandedOver;
use App\Notifications\Sarpras\LoanRejected;
use App\Notifications\Sarpras\LoanStepApproved;
use App\Notifications\Sarpras\LoanSubmitted;
use App\Services\MaintenanceService;
use Illuminate\Support\Facades\Notification;

function notificationFlow(array $steps): ApprovalFlow
{
    return ApprovalFlow::updateOrCreate(
        ['module' => 'loan'],
        ['name' => 'Alur Persetujuan Peminjaman', 'steps' => $steps, 'is_active' => true],
    );
}

test('submitting a loan notifies the first approver', function () {
    Notification::fake();

    $steps = [['role' => 'admin', 'title' => 'Verifikasi Admin']];
    notificationFlow($steps);
    $approver = roleUser('admin', ['loan.approve']);
    $borrower = sarprasUser(['loan.create']);
    $asset = Asset::factory()->create();

    $this->actingAs($borrower)->post(route('loans.store'), [
        'purpose' => 'Rapat koordinasi',
        'borrowed_at' => '2026-12-01 08:00',
        'due_at' => '2026-12-03 08:00',
        'asset_ids' => [$asset->id],
        'room_ids' => [],
    ])->assertRedirect();

    Notification::assertSentTo(
        $approver,
        LoanSubmitted::class,
        fn (LoanSubmitted $notification) => $notification->toArray($approver)['type'] === 'loan-submitted',
    );
});

test('approving a step notifies the next approver', function () {
    Notification::fake();

    $steps = [
        ['role' => 'admin', 'title' => 'Verifikasi Admin'],
        ['role' => 'pimpinan', 'title' => 'Persetujuan Pimpinan'],
    ];
    notificationFlow($steps);
    $nextApprover = roleUser('pimpinan', ['loan.approve']);
    $loan = LoanRequest::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('admin', ['loan.approve']))
        ->post(route('loans.approve', $loan));

    Notification::assertSentTo($nextApprover, LoanStepApproved::class);
});

test('the final approval notifies the borrower', function () {
    Notification::fake();

    $steps = [['role' => 'admin', 'title' => 'Verifikasi Admin']];
    notificationFlow($steps);
    $loan = LoanRequest::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('admin', ['loan.approve']))
        ->post(route('loans.approve', $loan));

    Notification::assertSentTo($loan->user, LoanApproved::class);
});

test('rejecting a loan notifies the borrower', function () {
    Notification::fake();

    $steps = [['role' => 'admin', 'title' => 'Verifikasi Admin']];
    notificationFlow($steps);
    $loan = LoanRequest::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('admin', ['loan.approve']))
        ->post(route('loans.reject', $loan), ['note' => 'Tidak tersedia']);

    Notification::assertSentTo($loan->user, LoanRejected::class);
});

test('handing over a loan notifies the borrower', function () {
    Notification::fake();

    $loan = LoanRequest::factory()->create(['status' => LoanStatus::Approved]);
    $asset = Asset::factory()->create();
    $loan->items()->create(['asset_id' => $asset->id]);

    $this->actingAs(sarprasUser(['loan.approve']))
        ->post(route('loans.handover', $loan));

    Notification::assertSentTo($loan->user, LoanHandedOver::class);
});

test('returning an asset with an open report keeps it under repair', function () {
    $loan = LoanRequest::factory()->create(['status' => LoanStatus::Active]);
    $asset = Asset::factory()->create(['status' => AssetStatus::Dipinjam]);
    $loan->items()->create(['asset_id' => $asset->id]);
    MaintenanceReport::factory()->create(['asset_id' => $asset->id]);

    $this->actingAs(sarprasUser(['loan.approve']))
        ->post(route('loans.return', $loan));

    expect($asset->fresh()->status)->toBe(AssetStatus::DalamPerbaikan);
});

test('the maintenance service can resynchronise an asset with no open reports', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::DalamPerbaikan]);

    app(MaintenanceService::class)->syncAssetStatus($asset);

    expect($asset->fresh()->status)->toBe(AssetStatus::Aktif);
});

test('cancelling a loan notifies the current approver', function () {
    Notification::fake();

    $steps = [['role' => 'admin', 'title' => 'Verifikasi Admin']];
    notificationFlow($steps);
    $approver = roleUser('admin', ['loan.approve']);
    $borrower = sarprasUser(['loan.create']);
    $loan = LoanRequest::factory()->create([
        'user_id' => $borrower->id,
        'status' => LoanStatus::Pending,
        'approval_steps' => $steps,
    ]);

    $this->actingAs($borrower)->post(route('loans.cancel', $loan));

    Notification::assertSentTo($approver, LoanCancelled::class);
});

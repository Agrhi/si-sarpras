<?php

use App\Enums\LoanStatus;
use App\Models\LoanRequest;
use App\Models\User;
use App\Notifications\Sarpras\LoanDueSoon;
use App\Notifications\Sarpras\LoanOverdue;
use Illuminate\Support\Facades\Notification;

test('due soon loans receive a single reminder', function () {
    Notification::fake();

    $loan = LoanRequest::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->addHours(12),
    ]);

    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();

    Notification::assertSentTo($loan->user, LoanDueSoon::class);
    expect($loan->fresh()->due_reminder_sent_at)->not->toBeNull();

    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();

    Notification::assertSentToTimes($loan->user, LoanDueSoon::class, 1);
});

test('loans beyond the reminder window are ignored', function () {
    Notification::fake();

    $loan = LoanRequest::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->addWeek(),
    ]);

    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();

    Notification::assertNotSentTo($loan->user, LoanDueSoon::class);
    expect($loan->fresh()->due_reminder_sent_at)->toBeNull();
});

test('overdue loans are reminded once by default', function () {
    Notification::fake();

    $loan = LoanRequest::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->subDay(),
    ]);

    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();
    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();

    Notification::assertSentToTimes($loan->user, LoanOverdue::class, 1);
    expect($loan->fresh()->overdue_reminder_sent_at)->not->toBeNull();
});

test('overdue reminders repeat daily when configured', function () {
    Notification::fake();
    config(['sarpras.notify_overdue_daily' => true]);

    $loan = LoanRequest::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->subDay(),
        'overdue_reminder_sent_at' => now()->subDay(),
    ]);

    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();

    Notification::assertSentTo($loan->user, LoanOverdue::class);
});

test('returned loans are not reminded', function () {
    Notification::fake();

    $loan = LoanRequest::factory()->create([
        'status' => LoanStatus::Returned,
        'due_at' => now()->subDay(),
        'returned_at' => now(),
    ]);

    $this->artisan('sarpras:send-loan-reminders')->assertSuccessful();

    Notification::assertNotSentTo($loan->user, LoanDueSoon::class);
    Notification::assertNotSentTo($loan->user, LoanOverdue::class);
});

test('the due reminder notification carries a message, url, and type', function () {
    $loan = LoanRequest::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->addDay(),
    ]);

    $payload = (new LoanDueSoon($loan))->toArray(new User);

    expect($payload)
        ->toHaveKeys(['message', 'url', 'type', 'loan_request_id'])
        ->and($payload['type'])->toBe('loan-due-soon')
        ->and($payload['url'])->toBe(route('loans.show', $loan));
});

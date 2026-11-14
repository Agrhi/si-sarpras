<?php

use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Notifications\Sarpras\MaintenanceScheduleDue;
use Illuminate\Support\Facades\Notification;

test('a due schedule notifies its assignee once per day', function () {
    Notification::fake();

    $technician = roleUser('petugas-teknis');
    $schedule = MaintenanceSchedule::factory()->dueToday()->create([
        'assigned_to' => $technician->id,
    ]);

    $this->artisan('sarpras:send-maintenance-reminders')->assertSuccessful();

    Notification::assertSentTo($technician, MaintenanceScheduleDue::class);
    expect($schedule->fresh()->last_reminded_on->toDateString())->toBe(today()->toDateString());

    $this->artisan('sarpras:send-maintenance-reminders')->assertSuccessful();

    Notification::assertSentToTimes($technician, MaintenanceScheduleDue::class, 1);
});

test('a schedule without an assignee notifies all technicians', function () {
    Notification::fake();

    $first = roleUser('petugas-teknis');
    $second = roleUser('petugas-teknis');
    MaintenanceSchedule::factory()->overdue()->create(['assigned_to' => null]);

    $this->artisan('sarpras:send-maintenance-reminders')->assertSuccessful();

    Notification::assertSentTo($first, MaintenanceScheduleDue::class);
    Notification::assertSentTo($second, MaintenanceScheduleDue::class);
});

test('inactive schedules and schedules outside the window are not reminded', function () {
    Notification::fake();

    $technician = roleUser('petugas-teknis');
    MaintenanceSchedule::factory()->create([
        'assigned_to' => $technician->id,
        'is_active' => false,
        'next_due_at' => now()->toDateString(),
    ]);
    MaintenanceSchedule::factory()->create([
        'assigned_to' => $technician->id,
        'next_due_at' => now()->addMonth()->toDateString(),
    ]);

    $this->artisan('sarpras:send-maintenance-reminders')->assertSuccessful();

    Notification::assertNotSentTo($technician, MaintenanceScheduleDue::class);
});

test('a schedule reminded yesterday is reminded again today', function () {
    Notification::fake();

    $technician = roleUser('petugas-teknis');
    MaintenanceSchedule::factory()->dueToday()->create([
        'assigned_to' => $technician->id,
        'last_reminded_on' => today()->subDay()->toDateString(),
    ]);

    $this->artisan('sarpras:send-maintenance-reminders')->assertSuccessful();

    Notification::assertSentTo($technician, MaintenanceScheduleDue::class);
});

test('the reminder notification points to the schedule list', function () {
    $schedule = MaintenanceSchedule::factory()->dueToday()->create();

    $payload = (new MaintenanceScheduleDue($schedule))->toArray(new User);

    expect($payload)
        ->toHaveKeys(['message', 'url', 'type', 'maintenance_schedule_id'])
        ->and($payload['type'])->toBe('maintenance-schedule-due')
        ->and($payload['url'])->toBe(route('maintenance-schedules.index'));
});

test('a schedule with nobody to notify is retried the next day', function () {
    Notification::fake();

    $schedule = MaintenanceSchedule::factory()->dueToday()->create(['assigned_to' => null]);

    $this->artisan('sarpras:send-maintenance-reminders')->assertSuccessful();

    expect($schedule->fresh()->last_reminded_on)->toBeNull();
});

test('both reminder commands are registered on the daily schedule', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('sarpras:send-loan-reminders')
        ->expectsOutputToContain('sarpras:send-maintenance-reminders')
        ->assertSuccessful();
});

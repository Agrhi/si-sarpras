<?php

use App\Enums\AssetStatus;
use App\Enums\MaintenanceFrequency;
use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use App\Models\User;

test('guests are redirected to login when visiting maintenance schedules', function () {
    $this->get(route('maintenance-schedules.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view maintenance schedules', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('maintenance-schedules.index'))->assertForbidden();
});

test('a schedule can be created for an asset', function () {
    $user = sarprasUser(['maintenance-schedule.create']);
    $technician = roleUser('petugas-teknis');
    $asset = Asset::factory()->create();

    $response = $this->actingAs($user)->post(route('maintenance-schedules.store'), [
        'asset_id' => $asset->id,
        'assigned_to' => $technician->id,
        'title' => 'Pembersihan filter',
        'frequency' => MaintenanceFrequency::Monthly->value,
        'next_due_at' => now()->addDays(5)->toDateString(),
        'is_active' => true,
    ]);

    $response->assertRedirect(route('maintenance-schedules.index'));

    $schedule = MaintenanceSchedule::firstWhere('title', 'Pembersihan filter');
    expect($schedule)->not->toBeNull();
    expect($schedule->assigned_to)->toBe($technician->id);
    expect($schedule->is_active)->toBeTrue();
});

test('a schedule requires an existing asset and frequency', function () {
    $user = sarprasUser(['maintenance-schedule.create']);

    $response = $this->actingAs($user)->post(route('maintenance-schedules.store'), [
        'asset_id' => 999,
        'title' => 'Servis',
        'frequency' => 'tidak-valid',
        'next_due_at' => now()->toDateString(),
    ]);

    $response->assertSessionHasErrors(['asset_id', 'frequency']);
    expect(MaintenanceSchedule::count())->toBe(0);
});

test('a schedule can be updated', function () {
    $user = sarprasUser(['maintenance-schedule.edit']);
    $schedule = MaintenanceSchedule::factory()->create(['title' => 'Lama']);

    $this->actingAs($user)->put(route('maintenance-schedules.update', $schedule), [
        'asset_id' => $schedule->asset_id,
        'title' => 'Baru',
        'frequency' => MaintenanceFrequency::Weekly->value,
        'next_due_at' => now()->addWeek()->toDateString(),
        'is_active' => false,
    ])->assertRedirect(route('maintenance-schedules.index'));

    $schedule = $schedule->fresh();
    expect($schedule->title)->toBe('Baru');
    expect($schedule->frequency)->toBe(MaintenanceFrequency::Weekly);
    expect($schedule->is_active)->toBeFalse();
});

test('a schedule without realizations is deleted', function () {
    $user = sarprasUser(['maintenance-schedule.delete']);
    $schedule = MaintenanceSchedule::factory()->create(['last_performed_at' => null]);

    $this->actingAs($user)
        ->delete(route('maintenance-schedules.destroy', $schedule))
        ->assertRedirect(route('maintenance-schedules.index'));

    expect(MaintenanceSchedule::find($schedule->id))->toBeNull();
});

test('a schedule with realizations is deactivated instead of deleted', function () {
    $user = sarprasUser(['maintenance-schedule.delete']);
    $schedule = MaintenanceSchedule::factory()->create([
        'last_performed_at' => now()->subMonth()->toDateString(),
    ]);

    $this->actingAs($user)
        ->delete(route('maintenance-schedules.destroy', $schedule))
        ->assertRedirect(route('maintenance-schedules.index'));

    $schedule = $schedule->fresh();
    expect($schedule)->not->toBeNull();
    expect($schedule->is_active)->toBeFalse();
});

test('recording a realization advances the due date by the frequency', function () {
    $user = sarprasUser(['maintenance-schedule.execute']);
    $schedule = MaintenanceSchedule::factory()->create([
        'frequency' => MaintenanceFrequency::Monthly->value,
        'last_reminded_on' => now()->toDateString(),
    ]);
    $performedOn = now()->startOfDay();

    $this->actingAs($user)->patch(route('maintenance-schedules.complete', $schedule), [
        'performed_at' => $performedOn->toDateString(),
    ])->assertRedirect();

    $schedule = $schedule->fresh();
    expect($schedule->last_performed_at->toDateString())->toBe($performedOn->toDateString());
    expect($schedule->next_due_at->toDateString())->toBe(now()->startOfDay()->addMonth()->toDateString());
    expect($schedule->last_reminded_on)->toBeNull();
});

test('the index filter shows only overdue schedules', function () {
    $user = sarprasUser(['maintenance-schedule.view']);
    MaintenanceSchedule::factory()->overdue()->create();
    MaintenanceSchedule::factory()->create(['next_due_at' => now()->addMonth()->toDateString()]);

    $response = $this->actingAs($user)->get(route('maintenance-schedules.index', [
        'state' => 'terlambat',
    ]));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/maintenance-schedules/index')
        ->has('schedules', 1)
        ->where('schedules.0.due_state', 'terlambat')
    );
});

test('the create page lists candidate technicians', function () {
    $user = sarprasUser(['maintenance-schedule.create']);
    roleUser('petugas-teknis');
    Asset::factory()->create();

    $response = $this->actingAs($user)->get(route('maintenance-schedules.create'));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/maintenance-schedules/create')
        ->has('assets', 1)
        ->has('technicians', 1)
        ->has('frequencies', count(MaintenanceFrequency::cases()))
    );
});

test('a schedule cannot be created for a deleted asset', function () {
    $user = sarprasUser(['maintenance-schedule.create']);
    $asset = Asset::factory()->create(['status' => AssetStatus::Dihapus]);

    $this->actingAs($user)->post(route('maintenance-schedules.store'), [
        'asset_id' => $asset->id,
        'title' => 'Servis',
        'frequency' => MaintenanceFrequency::Monthly->value,
        'next_due_at' => now()->addWeek()->toDateString(),
    ])->assertSessionHasErrors('asset_id');

    expect(MaintenanceSchedule::where('asset_id', $asset->id)->count())->toBe(0);
});

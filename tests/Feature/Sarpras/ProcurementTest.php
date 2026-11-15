<?php

use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Models\ApprovalFlow;
use App\Models\Asset;
use App\Models\Procurement;
use App\Models\User;

function procurementFlow(array $steps): ApprovalFlow
{
    return ApprovalFlow::updateOrCreate(
        ['module' => 'procurement'],
        ['name' => 'Alur Persetujuan Pengadaan', 'steps' => $steps, 'is_active' => true],
    );
}

test('guests are redirected to login when visiting procurements', function () {
    $this->get(route('procurements.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view procurements', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('procurements.index'))->assertForbidden();
});

test('non-approvers only see their own procurements', function () {
    $owner = sarprasUser(['procurement.view', 'procurement.create']);
    Procurement::factory()->create(['user_id' => User::factory()->create()->id]);
    Procurement::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('procurements.index'));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/procurements/index')
        ->has('procurements', 1)
    );
});

test('procurement can be submitted with items', function () {
    $user = sarprasUser(['procurement.create']);
    procurementFlow([['role' => 'admin', 'title' => 'Verifikasi Admin']]);

    $response = $this->actingAs($user)->post(route('procurements.store'), [
        'title' => 'Pengadaan laptop',
        'reason' => 'Kebutuhan laboratorium',
        'items' => [
            ['name' => 'Laptop', 'quantity' => 2, 'unit_price' => 7500000],
            ['name' => 'Mouse', 'quantity' => 2, 'unit_price' => 150000],
        ],
    ]);

    $procurement = Procurement::where('title', 'Pengadaan laptop')->first();

    $response->assertRedirect(route('procurements.show', $procurement));
    expect($procurement->status)->toBe(ProcurementStatus::Pending)
        ->and($procurement->items()->count())->toBe(2)
        ->and($procurement->approval_steps)->toBe([['role' => 'admin', 'title' => 'Verifikasi Admin']]);
});

test('procurement submission requires at least one item', function () {
    $user = sarprasUser(['procurement.create']);
    procurementFlow([['role' => 'admin', 'title' => 'Verifikasi Admin']]);

    $response = $this->actingAs($user)->post(route('procurements.store'), [
        'title' => 'Pengadaan',
        'reason' => 'Alasan',
        'items' => [],
    ]);

    $response->assertSessionHasErrors('items');
    expect(Procurement::count())->toBe(0);
});

test('procurement submission requires configured approval flow', function () {
    $user = sarprasUser(['procurement.create']);

    $response = $this->actingAs($user)->post(route('procurements.store'), [
        'title' => 'Pengadaan',
        'reason' => 'Alasan',
        'items' => [['name' => 'Laptop', 'quantity' => 1]],
    ]);

    $response->assertSessionHasErrors('items');
    expect(Procurement::count())->toBe(0);
});

test('procurement advances through multi-step approval flow', function () {
    $steps = [
        ['role' => 'admin', 'title' => 'Verifikasi Admin'],
        ['role' => 'pimpinan', 'title' => 'Persetujuan Pimpinan'],
    ];
    procurementFlow($steps);
    $procurement = Procurement::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('admin', ['procurement.approve']))
        ->post(route('procurements.approve', $procurement));

    expect($procurement->fresh()->status)->toBe(ProcurementStatus::Pending)
        ->and($procurement->fresh()->current_step)->toBe(1);

    $this->actingAs(roleUser('pimpinan', ['procurement.approve']))
        ->post(route('procurements.approve', $procurement), ['note' => 'Silakan']);

    expect($procurement->fresh()->status)->toBe(ProcurementStatus::Approved)
        ->and($procurement->fresh()->approvals()->count())->toBe(2);
});

test('procurement rejection stops the flow', function () {
    $steps = [['role' => 'admin', 'title' => 'Verifikasi Admin']];
    $procurement = Procurement::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('admin', ['procurement.approve']))
        ->post(route('procurements.reject', $procurement), ['note' => 'Anggaran habis']);

    $procurement = $procurement->fresh();
    expect($procurement->status)->toBe(ProcurementStatus::Rejected)
        ->and($procurement->approvals()->first()->note)->toBe('Anggaran habis');
});

test('approval requires the role of the current step', function () {
    $steps = [['role' => 'admin', 'title' => 'Verifikasi Admin']];
    $procurement = Procurement::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('pimpinan', ['procurement.approve']))
        ->post(route('procurements.approve', $procurement))
        ->assertForbidden();
});

test('deciding an already processed procurement is rejected', function () {
    $procurement = Procurement::factory()->create([
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
        'status' => ProcurementStatus::Rejected,
    ]);

    $this->actingAs(roleUser('admin', ['procurement.approve']))
        ->post(route('procurements.approve', $procurement))
        ->assertStatus(409);
});

test('receiving a procurement registers one asset per unit', function () {
    $procurement = Procurement::factory()->create([
        'status' => ProcurementStatus::Approved,
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
    ]);
    $procurement->items()->create(['name' => 'Laptop', 'quantity' => 2]);
    $procurement->items()->create(['name' => 'Proyektor', 'quantity' => 1]);

    $this->actingAs(sarprasUser(['procurement.receive']))
        ->post(route('procurements.receive', $procurement));

    $procurement = $procurement->fresh();

    expect($procurement->status)->toBe(ProcurementStatus::Received)
        ->and($procurement->received_at)->not->toBeNull()
        ->and(Asset::count())->toBe(3)
        ->and(Asset::where('name', 'Laptop')->count())->toBe(2)
        ->and(Asset::where('status', AssetStatus::Aktif)->count())->toBe(3);
});

test('receiving an unapproved procurement is rejected', function () {
    $procurement = Procurement::factory()->create();

    $this->actingAs(sarprasUser(['procurement.receive']))
        ->post(route('procurements.receive', $procurement))
        ->assertStatus(409);

    expect(Asset::count())->toBe(0);
});

test('procurement show page renders steps and items', function () {
    $user = roleUser('admin', ['procurement.view', 'procurement.approve']);
    $procurement = Procurement::factory()->create([
        'user_id' => $user->id,
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
    ]);
    $procurement->items()->create(['name' => 'Laptop', 'quantity' => 1]);

    $response = $this->actingAs($user)->get(route('procurements.show', $procurement));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/procurements/show')
        ->where('procurement.id', $procurement->id)
        ->has('items', 1)
        ->has('procurement.steps', 1)
        ->where('procurement.steps.0.state', 'current')
        ->where('can_approve', true)
    );
});

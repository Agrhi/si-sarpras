<?php

use App\Enums\AssetStatus;
use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Models\ApprovalFlow;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\User;

function disposalFlow(array $steps): ApprovalFlow
{
    return ApprovalFlow::updateOrCreate(
        ['module' => 'disposal'],
        ['name' => 'Alur Persetujuan Penghapusan', 'steps' => $steps, 'is_active' => true],
    );
}

test('guests are redirected to login when visiting disposals', function () {
    $this->get(route('disposals.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view disposals', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('disposals.index'))->assertForbidden();
});

test('non-approvers only see their own disposals', function () {
    $owner = sarprasUser(['disposal.view', 'disposal.create']);
    Disposal::factory()->create(['user_id' => User::factory()->create()->id]);
    Disposal::factory()->create(['user_id' => $owner->id]);

    $response = $this->actingAs($owner)->get(route('disposals.index'));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/disposals/index')
        ->has('disposals', 1)
    );
});

test('disposal can be submitted for an asset', function () {
    $user = sarprasUser(['disposal.create']);
    disposalFlow([['role' => 'admin', 'title' => 'Verifikasi Admin']]);
    $asset = Asset::factory()->create();

    $response = $this->actingAs($user)->post(route('disposals.store'), [
        'asset_id' => $asset->id,
        'reason' => 'Aset rusak berat',
    ]);

    $disposal = Disposal::where('asset_id', $asset->id)->first();

    $response->assertRedirect(route('disposals.show', $disposal));
    expect($disposal->status)->toBe(DisposalStatus::Pending)
        ->and($disposal->approval_steps)->toBe([['role' => 'admin', 'title' => 'Verifikasi Admin']]);
});

test('disposal submission rejects an asset with a running proposal', function () {
    $user = sarprasUser(['disposal.create']);
    disposalFlow([['role' => 'admin', 'title' => 'Verifikasi Admin']]);
    $disposal = Disposal::factory()->create(['status' => DisposalStatus::Pending]);

    $response = $this->actingAs($user)->post(route('disposals.store'), [
        'asset_id' => $disposal->asset_id,
        'reason' => 'Ajukan ulang',
    ]);

    $response->assertSessionHasErrors('asset_id');
    expect(Disposal::count())->toBe(1);
});

test('disposal submission rejects an already disposed asset', function () {
    $user = sarprasUser(['disposal.create']);
    disposalFlow([['role' => 'admin', 'title' => 'Verifikasi Admin']]);
    $asset = Asset::factory()->create(['status' => AssetStatus::Dihapus]);

    $response = $this->actingAs($user)->post(route('disposals.store'), [
        'asset_id' => $asset->id,
        'reason' => 'Aset sudah dihapus',
    ]);

    $response->assertSessionHasErrors('asset_id');
    expect(Disposal::count())->toBe(0);
});

test('disposal submission requires configured approval flow', function () {
    $user = sarprasUser(['disposal.create']);
    $asset = Asset::factory()->create();

    $response = $this->actingAs($user)->post(route('disposals.store'), [
        'asset_id' => $asset->id,
        'reason' => 'Aset rusak berat',
    ]);

    $response->assertSessionHasErrors('asset_id');
    expect(Disposal::count())->toBe(0);
});

test('disposal advances through multi-step approval flow', function () {
    $steps = [
        ['role' => 'admin', 'title' => 'Verifikasi Admin'],
        ['role' => 'pimpinan', 'title' => 'Persetujuan Pimpinan'],
    ];
    disposalFlow($steps);
    $disposal = Disposal::factory()->create(['approval_steps' => $steps]);

    $this->actingAs(roleUser('admin', ['disposal.approve']))
        ->post(route('disposals.approve', $disposal));

    expect($disposal->fresh()->status)->toBe(DisposalStatus::Pending)
        ->and($disposal->fresh()->current_step)->toBe(1);

    $this->actingAs(roleUser('pimpinan', ['disposal.approve']))
        ->post(route('disposals.approve', $disposal), ['note' => 'Silakan']);

    expect($disposal->fresh()->status)->toBe(DisposalStatus::Approved)
        ->and($disposal->fresh()->approvals()->count())->toBe(2);
});

test('disposal rejection stops the flow', function () {
    $disposal = Disposal::factory()->create([
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
    ]);

    $this->actingAs(roleUser('admin', ['disposal.approve']))
        ->post(route('disposals.reject', $disposal), ['note' => 'Masih layak pakai']);

    $disposal = $disposal->fresh();
    expect($disposal->status)->toBe(DisposalStatus::Rejected)
        ->and($disposal->approvals()->first()->note)->toBe('Masih layak pakai');
});

test('approval requires the role of the current step', function () {
    $disposal = Disposal::factory()->create([
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
    ]);

    $this->actingAs(roleUser('pimpinan', ['disposal.approve']))
        ->post(route('disposals.approve', $disposal))
        ->assertForbidden();
});

test('deciding an already processed disposal is rejected', function () {
    $disposal = Disposal::factory()->create([
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
        'status' => DisposalStatus::Rejected,
    ]);

    $this->actingAs(roleUser('admin', ['disposal.approve']))
        ->post(route('disposals.approve', $disposal))
        ->assertStatus(409);
});

test('completing a disposal marks the asset as removed', function () {
    $asset = Asset::factory()->create();
    $disposal = Disposal::factory()->create([
        'asset_id' => $asset->id,
        'status' => DisposalStatus::Approved,
    ]);

    $this->actingAs(sarprasUser(['disposal.complete']))
        ->post(route('disposals.complete', $disposal), [
            'method' => DisposalMethod::Sold->value,
            'realized_at' => '2026-11-15',
        ]);

    $disposal = $disposal->fresh();
    expect($disposal->status)->toBe(DisposalStatus::Completed)
        ->and($disposal->method)->toBe(DisposalMethod::Sold)
        ->and($disposal->realized_at)->not->toBeNull()
        ->and($asset->fresh()->status)->toBe(AssetStatus::Dihapus);
});

test('completing an unapproved disposal is rejected', function () {
    $disposal = Disposal::factory()->create();

    $this->actingAs(sarprasUser(['disposal.complete']))
        ->post(route('disposals.complete', $disposal), [
            'method' => DisposalMethod::Destroyed->value,
            'realized_at' => '2026-11-15',
        ])
        ->assertStatus(409);

    expect($disposal->fresh()->status)->toBe(DisposalStatus::Pending);
});

test('a borrowed asset cannot be removed', function () {
    $asset = Asset::factory()->create(['status' => AssetStatus::Dipinjam]);
    $disposal = Disposal::factory()->create([
        'asset_id' => $asset->id,
        'status' => DisposalStatus::Approved,
    ]);

    $this->actingAs(sarprasUser(['disposal.complete']))
        ->post(route('disposals.complete', $disposal), [
            'method' => DisposalMethod::Sold->value,
            'realized_at' => '2026-11-15',
        ])
        ->assertStatus(409);

    expect($asset->fresh()->status)->toBe(AssetStatus::Dipinjam)
        ->and($disposal->fresh()->status)->toBe(DisposalStatus::Approved);
});

test('disposal show page renders steps and asset', function () {
    $user = roleUser('admin', ['disposal.view', 'disposal.approve']);
    $disposal = Disposal::factory()->create([
        'user_id' => $user->id,
        'approval_steps' => [['role' => 'admin', 'title' => 'Verifikasi Admin']],
    ]);

    $response = $this->actingAs($user)->get(route('disposals.show', $disposal));

    $response->assertOk()->assertInertia(fn ($assert) => $assert
        ->component('sarpras/disposals/show')
        ->where('disposal.id', $disposal->id)
        ->has('disposal.steps', 1)
        ->where('disposal.steps.0.state', 'current')
        ->where('can_approve', true)
    );
});

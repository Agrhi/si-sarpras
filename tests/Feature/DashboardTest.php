<?php

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\LoanRequest;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('dashboard summarises assets and loans for permitted users', function () {
    $user = sarprasUser(['asset.view', 'loan.view']);

    Asset::factory()->count(2)->create([
        'status' => AssetStatus::Aktif,
        'condition' => AssetCondition::Baik,
    ]);
    Asset::factory()->create([
        'status' => AssetStatus::Dihapus,
        'condition' => AssetCondition::Rusak,
    ]);
    LoanRequest::factory()->create([
        'status' => LoanStatus::Active->value,
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->where('sarpras.assets.total', 3)
            ->where('sarpras.assets.byStatus.0.count', 2)
            ->where('sarpras.assets.byStatus.3.count', 1)
            ->where('sarpras.assets.byCondition.2.count', 1)
            ->where('sarpras.loans.active', 1)
            ->where('sarpras.loans.dueSoon', 1)
            ->where('sarpras.loans.overdue', 0)
            ->missing('sarpras.maintenance'));
});

test('dashboard only includes sarpras sections the user may view', function () {
    $user = sarprasUser(['loan.view']);

    LoanRequest::factory()->create([
        'status' => LoanStatus::Active->value,
        'due_at' => now()->subDays(2),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($assert) => $assert
            ->where('sarpras.loans.overdue', 1)
            ->missing('sarpras.assets')
            ->missing('sarpras.procurements'));
});

test('dashboard omits the sarpras summary without permissions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($assert) => $assert->where('sarpras', null));
});

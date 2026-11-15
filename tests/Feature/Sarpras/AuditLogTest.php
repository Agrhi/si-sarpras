<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

test('guests are redirected to login when visiting the audit log', function () {
    $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view the audit log', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('audit-logs.index'))->assertForbidden();
});

test('the auditor role can read the audit log', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $auditor = roleUser('auditor');

    $this->actingAs($auditor)->get(route('audit-logs.index'))->assertOk();
});

test('a successful write is recorded with its actor and subject', function () {
    $user = sarprasUser(['category.create']);

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Alat Tulis',
    ])->assertRedirect(route('categories.index'));

    $log = AuditLog::sole();

    expect($log->user_id)->toBe($user->id);
    expect($log->action)->toBe('categories.store');
    expect($log->summary)->toBe('Menambah kategori');
    expect($log->auditable_type)->toBeNull();
});

test('an update records the affected subject', function () {
    $user = sarprasUser(['category.edit']);
    $category = Category::factory()->create(['name' => 'Lama']);

    $this->actingAs($user)->put(route('categories.update', $category), [
        'name' => 'Baru',
    ])->assertRedirect(route('categories.index'));

    $log = AuditLog::sole();

    expect($log->summary)->toBe('Mengubah kategori Baru');
    expect($log->auditable_id)->toBe($category->id);
});

test('read-only requests are not recorded', function () {
    $user = sarprasUser(['category.view']);

    $this->actingAs($user)->get(route('categories.index'))->assertOk();

    expect(AuditLog::count())->toBe(0);
});

test('rejected input is not recorded', function () {
    $user = sarprasUser(['category.create']);

    $this->actingAs($user)->post(route('categories.store'), [
        'name' => '',
    ])->assertSessionHasErrors('name');

    expect(AuditLog::count())->toBe(0);
});

test('audit log can be filtered by user and date range', function () {
    $viewer = sarprasUser(['audit-log.view']);
    $alice = User::factory()->create(['name' => 'Alice']);
    $bob = User::factory()->create(['name' => 'Bob']);

    $record = function (User $user, string $summary, string $at): void {
        $log = AuditLog::create([
            'user_id' => $user->id,
            'action' => 'test.action',
            'summary' => $summary,
        ]);

        $log->forceFill(['created_at' => $at])->save();
    };

    $record($alice, 'Aksi Alice', '2026-11-05 09:00:00');
    $record($bob, 'Aksi Bob', '2026-11-06 09:00:00');
    $record($alice, 'Aksi Alice Lama', '2026-10-01 09:00:00');

    $this->actingAs($viewer)
        ->get(route('audit-logs.index', [
            'user_id' => $alice->id,
            'from' => '2026-11-01',
            'to' => '2026-11-30',
        ]))
        ->assertOk()
        ->assertInertia(fn ($assert) => $assert
            ->has('logs', 1)
            ->where('logs.0.summary', 'Aksi Alice')
            ->where('userId', $alice->id)
            ->where('from', '2026-11-01')
            ->where('to', '2026-11-30'));
});

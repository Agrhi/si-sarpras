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

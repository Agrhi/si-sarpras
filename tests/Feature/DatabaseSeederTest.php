<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;

test('database seeder populates master data and role users idempotently', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Category::count())->toBe(3)
        ->and(Location::count())->toBe(4)
        ->and(Room::count())->toBe(4)
        ->and(Asset::count())->toBe(12);

    $superadmin = User::where('email', 'superadmin@localhost')->firstOrFail();

    expect($superadmin->hasRole('super-admin'))->toBeTrue()
        ->and($superadmin->personalTeam())->not->toBeNull()
        ->and($superadmin->teams()->count())->toBe(1);

    foreach (['admin', 'pimpinan', 'pegawai'] as $role) {
        $user = User::where('email', "{$role}@localhost")->firstOrFail();

        expect($user->hasRole($role))->toBeTrue()
            ->and($user->personalTeam())->not->toBeNull()
            ->and($user->teams()->count())->toBe(1);
    }

    expect(Asset::where('inventory_code', 'INV-00001')->firstOrFail()->category->name)->toBe('Elektronik');
});

test('seeded superadmin can log in and reach the admin area', function () {
    $this->seed(DatabaseSeeder::class);

    $this->post(route('login.store'), [
        'email' => 'superadmin@localhost',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('admin.users.index'))->assertOk();
});

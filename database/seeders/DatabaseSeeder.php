<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $superadmin = User::firstOrCreate(
            ['email' => 'superadmin@localhost'],
            ['name' => 'Test User', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        if (! $superadmin->personalTeam()) {
            app(CreateTeam::class)->handle($superadmin, $superadmin->name."'s Team", isPersonal: true);
        }

        // call RoleAndPermissionSeeder
        $this->call([
            RoleAndPermissionSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}

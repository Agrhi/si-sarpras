<?php

namespace Database\Factories;

use App\Enums\DisposalStatus;
use App\Models\Asset;
use App\Models\Disposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disposal>
 */
class DisposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'reason' => fake()->paragraph(),
            'status' => DisposalStatus::Pending->value,
            'approval_steps' => [
                ['role' => 'admin', 'title' => 'Verifikasi Admin Sarpras'],
            ],
            'current_step' => 0,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\ProcurementStatus;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procurement>
 */
class ProcurementFactory extends Factory
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
            'title' => fake()->sentence(3),
            'reason' => fake()->paragraph(),
            'status' => ProcurementStatus::Pending->value,
            'approval_steps' => [
                ['role' => 'admin', 'title' => 'Verifikasi Admin Sarpras'],
            ],
            'current_step' => 0,
        ];
    }
}

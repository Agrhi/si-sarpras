<?php

namespace Database\Factories;

use App\Enums\MaintenanceFrequency;
use App\Models\Asset;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceSchedule>
 */
class MaintenanceScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'assigned_to' => User::factory(),
            'title' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'frequency' => fake()->randomElement(MaintenanceFrequency::cases())->value,
            'next_due_at' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'last_performed_at' => null,
            'last_reminded_on' => null,
            'is_active' => true,
        ];
    }

    /**
     * Mark the schedule as due today.
     */
    public function dueToday(): static
    {
        return $this->state(fn () => [
            'next_due_at' => now()->toDateString(),
        ]);
    }

    /**
     * Mark the schedule as overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'next_due_at' => now()->subDays(5)->toDateString(),
        ]);
    }
}

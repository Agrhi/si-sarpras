<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceUrgency;
use App\Models\Asset;
use App\Models\MaintenanceReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceReport>
 */
class MaintenanceReportFactory extends Factory
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
            'reported_by' => User::factory(),
            'assigned_to' => null,
            'urgency' => fake()->randomElement(MaintenanceUrgency::cases())->value,
            'description' => fake()->sentence(12),
            'photo_path' => null,
            'status' => MaintenanceStatus::Pending->value,
            'action_taken' => null,
            'cost' => null,
            'spare_parts' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Mark the report as being processed by a technician.
     */
    public function inProgress(): static
    {
        return $this->state(fn () => [
            'status' => MaintenanceStatus::InProgress->value,
            'assigned_to' => User::factory(),
            'started_at' => now(),
        ]);
    }

    /**
     * Mark the report as completed.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => MaintenanceStatus::Completed->value,
            'assigned_to' => User::factory(),
            'started_at' => now()->subDay(),
            'completed_at' => now(),
            'action_taken' => fake()->sentence(),
            'cost' => fake()->randomFloat(2, 10, 500),
        ]);
    }
}

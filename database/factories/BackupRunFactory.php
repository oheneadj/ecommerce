<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Models\BackupRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupRun>
 */
class BackupRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => BackupStatus::Success,
            'triggered_by' => null,
            'disk' => 'gdrive',
            'remote_path' => 'App/'.now()->format('Y-m-d-H-i-s').'.zip',
            'size_bytes' => fake()->numberBetween(1_000_000, 500_000_000),
            'error_message' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state([
            'status' => BackupStatus::Running,
            'completed_at' => null,
            'size_bytes' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => BackupStatus::Failed,
            'size_bytes' => null,
            'error_message' => 'Exception',
        ]);
    }
}

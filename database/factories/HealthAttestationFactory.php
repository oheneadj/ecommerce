<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HealthAttestation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthAttestation>
 */
class HealthAttestationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'backup_restore_tested',
            'confirmed_by' => User::factory(),
            'confirmed_at' => now(),
            'notes' => null,
        ];
    }
}

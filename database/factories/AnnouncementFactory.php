<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerSegment;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(12),
            'audience' => CustomerSegment::All,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'priority' => 0,
            'active' => true,
        ];
    }
}

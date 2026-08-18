<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\AnnouncementView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementView>
 */
class AnnouncementViewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'viewer_key' => 'guest_'.fake()->uuid(),
            'viewed_at' => now(),
            'dismissed_at' => null,
        ];
    }
}

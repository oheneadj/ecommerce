<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class StoreSettingSeeder extends Seeder
{
    public function run(): void
    {
        StoreSetting::current()->update([
            'business_name' => 'Demo Store',
            'tagline' => 'Quality goods, delivered fast.',
            'contact_email' => 'hello@demostore.test',
            'contact_phone' => '+233200000000',
            'contact_address' => 'Accra, Ghana',
            'primary_color' => '#111827',
            'secondary_color' => '#6366f1',
            'tax_rate' => 0,
            'stock_reservation_minutes' => 15,
            'low_stock_threshold' => 5,
        ]);
    }
}

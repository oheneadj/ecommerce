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
            'stock_reservation_minutes' => 15,
            'low_stock_threshold' => 5,
        ]);
    }
}

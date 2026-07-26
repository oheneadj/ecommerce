<?php

/**
 * Seeds one staff account per role, plus a handful of customer accounts.
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Ama Owusu',
            'email' => 'superadmin@example.com',
            'phone' => '0551000001',
        ]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $admin = User::factory()->create([
            'name' => 'Kwame Asante',
            'email' => 'admin@example.com',
            'phone' => '0551000002',
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $storeKeeper = User::factory()->create([
            'name' => 'Efua Mensah',
            'email' => 'storekeeper@example.com',
            'phone' => '0551000003',
        ]);
        $storeKeeper->assignRole(UserRole::StoreKeeper->value);

        // A phone+email customer (both notification channels available).
        User::factory()->create([
            'name' => 'Yaw Boateng',
            'email' => 'yaw@example.com',
            'phone' => '0551000010',
        ]);

        // A Google-only customer with no phone — exercises the
        // email-only notification fallback (no other seeder data depends
        // on this, it's here purely so the account exists to inspect).
        User::factory()->create([
            'name' => 'Abena Owusu',
            'email' => 'abena@example.com',
            'phone' => null,
            'google_id' => 'seed-google-id-abena',
        ]);

        User::factory()->count(5)->create();
    }
}

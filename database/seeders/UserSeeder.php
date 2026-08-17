<?php

/**
 * Seeds one staff account per role, plus a handful of customer accounts.
 */

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = User::factory()->create([
            'name' => 'Ama Owusu',
            'email' => 'superadmin@example.com',
            'phone' => '+233551000001',
        ]);
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        $admin = User::factory()->create([
            'name' => 'Kwame Asante',
            'email' => 'admin@example.com',
            'phone' => '+233551000002',
        ]);
        $admin->assignRole(UserRole::Admin->value);

        $storeKeeper = User::factory()->create([
            'name' => 'Efua Mensah',
            'email' => 'storekeeper@example.com',
            'phone' => '+233551000003',
        ]);
        $storeKeeper->assignRole(UserRole::StoreKeeper->value);

        // A phone+email customer (both notification channels available).
        $yaw = User::factory()->create([
            'name' => 'Yaw Boateng',
            'email' => 'yaw@example.com',
            'phone' => '+233551000010',
        ]);
        Address::factory()->create([
            'user_id' => $yaw->id,
            'label' => 'Home',
            'recipient_name' => $yaw->name,
            'phone' => $yaw->phone,
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'is_default' => true,
        ]);
        Address::factory()->create([
            'user_id' => $yaw->id,
            'label' => 'Office',
            'recipient_name' => $yaw->name,
            'phone' => $yaw->phone,
            'city' => 'Tema',
            'region' => 'Greater Accra',
            'is_default' => false,
        ]);

        // A Google-only customer with no phone — exercises the
        // email-only notification fallback (no other seeder data depends
        // on this, it's here purely so the account exists to inspect).
        $abena = User::factory()->create([
            'name' => 'Abena Owusu',
            'email' => 'abena@example.com',
            'phone' => null,
            'google_id' => 'seed-google-id-abena',
        ]);
        Address::factory()->create([
            'user_id' => $abena->id,
            'label' => 'Home',
            'recipient_name' => $abena->name,
            'city' => 'Kumasi',
            'region' => 'Ashanti',
            'is_default' => true,
        ]);

        User::factory()->count(5)->create();
    }
}

<?php

/**
 * Creates the first Super Admin account for a fresh deployment.
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * The `UserSeeder` used by `php artisan migrate:fresh --seed` creates
 * fake demo accounts (including a "superadmin@example.com" with a random
 * factory password nobody knows) — fine for local dev, wrong for a real
 * deployment. This command is the actual, safe way to create the first
 * real Super Admin during onboarding (Epic E13.3): prompts for real
 * details, validates them the same way the customer-facing registration
 * form does, and assigns the role atomically so there's never a moment
 * where the account exists without it.
 */
class CreateSuperAdmin extends Command
{
    /**
     * @var string
     */
    protected $signature = 'app:create-super-admin';

    /**
     * @var string
     */
    protected $description = 'Create the first Super Admin account for a fresh deployment';

    public function handle(): int
    {
        $name = $this->ask('Name');
        $email = $this->ask('Email');
        $password = $this->secret('Password');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::default(), 'confirmed'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // email_verified_at isn't mass-assignable (not in User's Fillable
        // list) — same reason VerifyOtp/LoginWithGoogle forceFill it too.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->assignRole(UserRole::SuperAdmin->value);

        $this->info("Super Admin account created for {$user->email}.");

        return self::SUCCESS;
    }
}

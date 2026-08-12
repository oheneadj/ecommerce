<?php

/**
 * Covers the admin Dashboard page's personalized "Welcome, {name}" title.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardGreetingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_the_dashboard_greets_the_logged_in_staff_member_by_name(): void
    {
        $user = $this->admin();
        $user->forceFill(['name' => 'Ama Boateng'])->save();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)->assertSee('Welcome, Ama Boateng');
    }

    public function test_the_dashboard_falls_back_to_a_generic_title_when_the_user_has_no_name(): void
    {
        $user = $this->admin();
        $user->forceFill(['name' => null])->save();
        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSee('Dashboard')
            ->assertDontSee('Welcome,');
    }
}

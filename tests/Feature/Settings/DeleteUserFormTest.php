<?php

/**
 * Covers the customer-facing "delete my account" settings form.
 */

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Livewire\Settings\DeleteUserForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DeleteUserFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_password_account_requires_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_a_password_account_can_delete_itself_with_the_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->set('password', 'correct-password')
            ->call('deleteUser');

        $this->assertTrue($user->fresh()->trashed());
    }

    /**
     * Regression: a phone-OTP-only or Google-only account never has a
     * password (`users.password` is null) — Laravel's `current_password`
     * rule always fails against a null hash, which made self-service
     * deletion permanently unreachable for that account, with no
     * alternate confirmation path offered.
     */
    public function test_a_password_less_account_can_delete_itself_without_a_password(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_the_password_field_is_hidden_for_a_password_less_account(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-123']);
        $this->actingAs($user);

        Livewire::test(DeleteUserForm::class)
            ->assertSet('hasPassword', false)
            ->assertDontSeeHtml('wire:model="password"');
    }
}

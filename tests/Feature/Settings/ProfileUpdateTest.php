<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->get('/settings/profile')->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $user->refresh();

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test(Profile::class)
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $response->assertHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        // Soft-deleted, not hard-deleted (so past orders/reviews still
        // resolve a name) — but email/phone/google_id are freed so the
        // customer can register a new account with the same one later.
        $user = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);
        $id = $user->id;

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $response
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertFalse(auth()->check());
        $this->assertNull(User::query()->find($id));

        $trashed = User::withTrashed()->find($id);
        $this->assertNotNull($trashed);
        $this->assertTrue($trashed->trashed());
        $this->assertSame("shopper@example.com-deleted-{$id}", $trashed->email);
        $this->assertSame("+233201234567-deleted-{$id}", $trashed->phone);
    }

    public function test_a_new_account_can_reuse_the_email_and_phone_of_a_deleted_account(): void
    {
        $user = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);

        $this->actingAs($user);
        Livewire::test('settings.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $newUser = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);

        $this->assertSame('shopper@example.com', $newUser->email);
        $this->assertNotSame($user->id, $newUser->id);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = Livewire::test('settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $response->assertHasErrors(['password']);

        $this->assertNotNull($user->fresh());
    }
}

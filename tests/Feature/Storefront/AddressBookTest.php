<?php

/**
 * Covers the customer-facing address book (/account/addresses) — add,
 * edit, delete, set default, and ownership enforcement via AddressPolicy.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\AddressBook;
use App\Models\Address;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/account/addresses')->assertRedirect('/login');
    }

    public function test_an_authenticated_customer_can_view_the_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account/addresses')->assertOk();
    }

    /**
     * The real content only ever reaches the page through a follow-up
     * request the #[Lazy] attribute defers to — the initial HTTP response
     * (what a customer's very first paint actually sees) must show the
     * skeleton, never a blank gap while that request is in flight.
     */
    public function test_the_page_shows_a_skeleton_placeholder_before_the_real_component_loads(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account/addresses')->assertOk()->assertSeeHtml('animate-pulse');
    }

    public function test_the_first_saved_address_is_automatically_the_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(AddressBook::class)
            ->call('startCreate')
            ->set('recipient_name', 'Jane Doe')
            ->set('phone', '+233551234567')
            ->set('line1', '123 Main St')
            ->set('city', 'Accra')
            ->call('save')
            ->assertHasNoErrors();

        $address = $user->addresses()->sole();
        $this->assertTrue($address->is_default);
    }

    public function test_a_second_address_is_not_default_unless_explicitly_set(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $this->actingAs($user);

        Livewire::test(AddressBook::class)
            ->call('startCreate')
            ->set('recipient_name', 'Jane Doe')
            ->set('phone', '+233551234567')
            ->set('line1', '123 Main St')
            ->set('city', 'Accra')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
        $this->assertSame(2, $user->addresses()->count());
    }

    public function test_setting_a_new_default_unsets_the_previous_one(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $second = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);
        $this->actingAs($user);

        Livewire::test(AddressBook::class)
            ->call('setDefault', $second->id);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_deleting_the_default_address_promotes_another_one(): void
    {
        $user = User::factory()->create();
        $older = Address::factory()->create(['user_id' => $user->id, 'is_default' => false]);
        $default = Address::factory()->create(['user_id' => $user->id, 'is_default' => true]);
        $this->actingAs($user);

        Livewire::test(AddressBook::class)
            ->call('delete', $default->id);

        $this->assertModelMissing($default);
        $this->assertTrue($older->fresh()->is_default);
    }

    public function test_a_customer_cannot_edit_another_customers_address(): void
    {
        $owner = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $owner->id]);

        $intruder = User::factory()->create();
        $this->actingAs($intruder);
        $this->withoutExceptionHandling();

        $this->expectException(AuthorizationException::class);

        Livewire::test(AddressBook::class)->call('startEdit', $address->id);
    }

    public function test_a_customer_cannot_delete_another_customers_address(): void
    {
        $owner = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $owner->id]);

        $intruder = User::factory()->create();
        $this->actingAs($intruder);
        $this->withoutExceptionHandling();

        $this->expectException(AuthorizationException::class);

        Livewire::test(AddressBook::class)->call('delete', $address->id);

        $this->assertModelExists($address);
    }

    public function test_editing_an_address_updates_it(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'city' => 'Kumasi']);
        $this->actingAs($user);

        Livewire::test(AddressBook::class)
            ->call('startEdit', $address->id)
            ->set('city', 'Accra')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Accra', $address->fresh()->city);
    }
}

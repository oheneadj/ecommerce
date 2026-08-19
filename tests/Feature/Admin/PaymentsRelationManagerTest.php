<?php

/**
 * Covers Orders' PaymentsRelationManager — Super-Admin-only, same
 * restriction as the standalone Payments resource, but rendered inline
 * via an explicit ->schema() (a real modal action, not a link to a
 * separately-gated page) — so unlike PaymentsTable's own ViewAction, this
 * one has no page-route canAccess() backstop and depends entirely on the
 * action's own authorization.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_the_view_action_is_hidden_for_admin_and_visible_for_super_admin(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id]);

        $this->actingAs($this->staff(UserRole::Admin));
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->assertTableActionHidden('view', $payment);

        $this->actingAs($this->staff(UserRole::SuperAdmin));
        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->assertTableActionVisible('view', $payment);
    }

    /**
     * The real fix: visible() alone only hides the button — it doesn't by
     * itself stop a crafted request from directly invoking the mounted
     * action, and unlike PaymentsTable's version, this action has no
     * page-route canAccess() to fall back on either. Filament treats an
     * unauthorized action as disabled (silently unmounted, not a hard
     * 403) — so the real proof is that the sensitive content (the
     * provider reference, part of the "raw provider callback metadata"
     * this gate exists to protect) never reaches the response at all when
     * an Admin tries to mount it directly.
     */
    public function test_the_view_action_cannot_be_invoked_directly_by_an_admin(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider_reference' => 'SECRET-REF-999']);

        $this->actingAs($this->staff(UserRole::Admin));

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->mountTableAction('view', $payment)
            ->assertDontSee('SECRET-REF-999');
    }

    public function test_a_super_admin_can_open_the_view_action(): void
    {
        $order = Order::factory()->create();
        $payment = Payment::factory()->create(['order_id' => $order->id, 'provider_reference' => 'REF-999']);

        $this->actingAs($this->staff(UserRole::SuperAdmin));

        Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => ViewOrder::class])
            ->mountTableAction('view', $payment)
            ->assertSuccessful();
    }
}

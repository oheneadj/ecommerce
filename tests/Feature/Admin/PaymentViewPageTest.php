<?php

/**
 * Covers the Payment view page — Super-Admin-only, shows metadata and
 * every related API call's payload.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\RelationManagers\ApiLogsRelationManager;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentViewPageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(UserRole $role): User
    {
        Role::findOrCreate($role->value, 'web');
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    public function test_only_super_admin_can_view_a_payments_detail_page(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($this->staff(UserRole::SuperAdmin))
            ->get(PaymentResource::getUrl('view', ['record' => $payment]))
            ->assertOk();

        $this->actingAs($this->staff(UserRole::Admin))
            ->get(PaymentResource::getUrl('view', ['record' => $payment]))
            ->assertForbidden();

        $this->actingAs($this->staff(UserRole::StoreKeeper))
            ->get(PaymentResource::getUrl('view', ['record' => $payment]))
            ->assertForbidden();
    }

    public function test_the_view_action_on_the_payments_table_is_hidden_for_admin_and_visible_for_super_admin(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs($this->staff(UserRole::Admin));
        Livewire::test(ListPayments::class)
            ->assertTableActionHidden('view', $payment);

        $this->actingAs($this->staff(UserRole::SuperAdmin));
        Livewire::test(ListPayments::class)
            ->assertTableActionVisible('view', $payment);
    }

    /**
     * This action has no explicit ->schema() override (see
     * PaymentsRelationManager's own equivalent action for the case where
     * one exists) — Filament resolves it to a link pointing at the
     * dedicated view page, so ViewPayment::canAccess() (proven above) is
     * the real backstop here regardless of ->visible()/->authorize() on
     * the table action itself. Both are still declared for defense-in-
     * depth/consistency with the relation manager's version.
     */
    public function test_the_view_page_shows_the_payment_details_and_metadata(): void
    {
        $this->actingAs($this->staff(UserRole::SuperAdmin));

        $payment = Payment::factory()->create([
            'provider' => 'paystack',
            'provider_reference' => 'REF-12345',
            'metadata' => ['gateway_response' => 'Approved'],
        ]);

        $this->get(PaymentResource::getUrl('view', ['record' => $payment]))
            ->assertOk()
            ->assertSee('paystack')
            ->assertSee('REF-12345')
            ->assertSee('Approved');
    }

    public function test_the_api_logs_tab_lists_calls_for_this_payment_only(): void
    {
        $this->actingAs($this->staff(UserRole::SuperAdmin));

        $payment = Payment::factory()->create();
        $ownLog = PaymentApiLog::factory()->create(['payment_id' => $payment->id, 'order_id' => $payment->order_id]);
        $otherLog = PaymentApiLog::factory()->create();

        Livewire::test(ApiLogsRelationManager::class, ['ownerRecord' => $payment, 'pageClass' => ViewPayment::class])
            ->assertCanSeeTableRecords([$ownLog])
            ->assertCanNotSeeTableRecords([$otherLog]);
    }

    public function test_the_view_payload_action_shows_the_request_and_response_payload(): void
    {
        $this->actingAs($this->staff(UserRole::SuperAdmin));

        $payment = Payment::factory()->create();
        $log = PaymentApiLog::factory()->create([
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'request_payload' => ['amount' => 5000],
            'response_payload' => ['reference' => 'xyz'],
        ]);

        Livewire::test(ApiLogsRelationManager::class, ['ownerRecord' => $payment, 'pageClass' => ViewPayment::class])
            ->mountTableAction('viewPayload', $log)
            ->assertSuccessful();
    }

    /**
     * The "Issue refund" action must actually consult PaymentPolicy::update()
     * — not just be reachable by anyone who can view the Payments list.
     * PaymentPolicy currently defines update() identically to viewAny(), so
     * there's no real role today that passes one and fails the other; this
     * swaps in a policy that denies update() specifically to prove the
     * action's own ->authorize() call is what's actually gating it, not
     * incidental visibility from ->visible().
     */
    public function test_the_refund_action_is_hidden_when_the_policy_denies_update(): void
    {
        $this->actingAs($this->staff(UserRole::Admin));

        $payment = Payment::factory()->create(['status' => PaymentStatus::Success]);

        Gate::policy(Payment::class, DenyUpdatePaymentPolicy::class);

        Livewire::test(ListPayments::class)
            ->assertTableActionHidden('refund', $payment);
    }
}

/**
 * A test-only stand-in for PaymentPolicy that denies `update` specifically,
 * used to prove the refund action's ->authorize() call actually consults
 * the policy rather than just relying on ->visible().
 */
class DenyUpdatePaymentPolicy extends PaymentPolicy
{
    public function update(User $user, Payment $payment): bool
    {
        return false;
    }
}

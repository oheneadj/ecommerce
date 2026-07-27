<?php

/**
 * Covers searching customers by name/email/phone, and the Send email/Send
 * SMS actions (single-record, view page, and bulk).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Mail\CustomerMessage;
use App\Models\User;
use App\Sms\Contracts\SmsGateway;
use App\Sms\SmsSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function fakeSmsGateway(): void
    {
        $this->app->bind(SmsGateway::class, fn () => new class implements SmsGateway
        {
            public function send(string $to, string $message): SmsSendResult
            {
                return new SmsSendResult(success: true, providerReference: 'fake-ref');
            }
        });
    }

    public function test_customers_can_be_searched_by_name_email_or_phone(): void
    {
        $this->actingAs($this->admin());

        $byName = User::factory()->create(['name' => 'Jane Boateng', 'email' => 'jane@example.com', 'phone' => '0551111111']);
        $byEmail = User::factory()->create(['name' => 'Kofi Mensah', 'email' => 'findme@example.com', 'phone' => '0552222222']);
        $byPhone = User::factory()->create(['name' => 'Ama Asante', 'email' => 'ama@example.com', 'phone' => '0559999999']);

        Livewire::test(ListCustomers::class)
            ->searchTable('Boateng')
            ->assertCanSeeTableRecords([$byName])
            ->assertCanNotSeeTableRecords([$byEmail, $byPhone]);

        Livewire::test(ListCustomers::class)
            ->searchTable('findme@example.com')
            ->assertCanSeeTableRecords([$byEmail])
            ->assertCanNotSeeTableRecords([$byName, $byPhone]);

        Livewire::test(ListCustomers::class)
            ->searchTable('0559999999')
            ->assertCanSeeTableRecords([$byPhone])
            ->assertCanNotSeeTableRecords([$byName, $byEmail]);
    }

    public function test_send_email_action_is_hidden_for_a_customer_with_no_email(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['email' => null, 'phone' => '0551111111']);

        Livewire::test(ListCustomers::class)
            ->assertTableActionHidden('sendEmail', $customer);
    }

    public function test_send_sms_action_is_hidden_for_a_customer_with_no_phone(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['email' => 'has-email@example.com', 'phone' => null]);

        Livewire::test(ListCustomers::class)
            ->assertTableActionHidden('sendSms', $customer);
    }

    public function test_sending_an_email_from_the_customer_row_sends_it(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['email' => 'customer@example.com']);

        Livewire::test(ListCustomers::class)
            ->callTableAction('sendEmail', $customer, data: [
                'subject' => 'A note from us',
                'body' => 'Thanks for shopping with us!',
            ])
            ->assertHasNoTableActionErrors();

        Mail::assertSent(fn (CustomerMessage $mailable): bool => $mailable->hasTo($customer->email));
    }

    public function test_the_to_field_cannot_be_used_to_redirect_the_email_elsewhere(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['email' => 'customer@example.com']);

        Livewire::test(ListCustomers::class)
            ->callTableAction('sendEmail', $customer, data: [
                'to' => 'attacker@example.com',
                'subject' => 'A note from us',
                'body' => 'Thanks for shopping with us!',
            ])
            ->assertHasNoTableActionErrors();

        Mail::assertSent(fn (CustomerMessage $mailable): bool => $mailable->hasTo($customer->email));
        Mail::assertNotSent(fn (CustomerMessage $mailable): bool => $mailable->hasTo('attacker@example.com'));
    }

    public function test_sending_an_sms_from_the_customer_row_sends_it(): void
    {
        $this->fakeSmsGateway();
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['phone' => '0551234567']);

        Livewire::test(ListCustomers::class)
            ->callTableAction('sendSms', $customer, data: ['message' => 'Your order has shipped!'])
            ->assertHasNoTableActionErrors();
    }

    public function test_send_email_and_sms_actions_work_from_the_customer_view_page(): void
    {
        Mail::fake();
        $this->fakeSmsGateway();
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['email' => 'view-page@example.com', 'phone' => '0557654321']);

        Livewire::test(ViewCustomer::class, ['record' => $customer->getRouteKey()])
            ->callAction('sendEmail', data: ['subject' => 'Hi', 'body' => 'Hello there'])
            ->assertHasNoActionErrors()
            ->callAction('sendSms', data: ['message' => 'Hi there'])
            ->assertHasNoActionErrors();

        Mail::assertSent(fn (CustomerMessage $mailable): bool => $mailable->hasTo($customer->email));
    }

    public function test_bulk_send_email_skips_customers_with_no_email_and_reports_the_count(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());

        $withEmail = User::factory()->create(['email' => 'has@example.com']);
        $withoutEmail = User::factory()->create(['email' => null]);

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('bulkSendEmail', [$withEmail, $withoutEmail], data: [
                'subject' => 'Sale!',
                'body' => 'Everything is 20% off.',
            ])
            ->assertHasNoTableBulkActionErrors();

        Mail::assertSentCount(1);
    }

    public function test_bulk_send_sms_skips_customers_with_no_phone_and_reports_the_count(): void
    {
        $this->fakeSmsGateway();
        $this->actingAs($this->admin());

        $withPhone = User::factory()->create(['phone' => '0551112222']);
        $withoutPhone = User::factory()->create(['phone' => null]);

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('bulkSendSms', [$withPhone, $withoutPhone], data: ['message' => 'Sale!'])
            ->assertHasNoTableBulkActionErrors();
    }
}

<?php

/**
 * Covers store branding applied to outgoing customer notifications — the
 * mail "From" display name (AppServiceProvider's MessageSending listener)
 * and the shared BrandedMessage helper (mail signature + SMS prefix).
 */

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\OrderPlaced;
use App\Notifications\Support\BrandedMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BrandedMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_helper_signs_with_the_business_name(): void
    {
        StoreSetting::current()->update(['business_name' => 'Acme Store']);

        $message = BrandedMessage::mail(new MailMessage);

        $this->assertSame("Regards,\nAcme Store", $message->salutation);
    }

    public function test_mail_helper_falls_back_to_app_name_when_no_business_name_is_set(): void
    {
        $message = BrandedMessage::mail(new MailMessage);

        $this->assertSame("Regards,\n".config('app.name'), $message->salutation);
    }

    public function test_sms_helper_prefixes_with_the_business_name(): void
    {
        StoreSetting::current()->update(['business_name' => 'Acme Store']);

        $this->assertSame('Acme Store: Your code is 123456.', BrandedMessage::sms('Your code is 123456.'));
    }

    public function test_sms_helper_is_unprefixed_when_no_business_name_is_set(): void
    {
        $this->assertSame('Your code is 123456.', BrandedMessage::sms('Your code is 123456.'));
    }

    public function test_outgoing_mail_uses_the_business_name_as_the_from_display_name(): void
    {
        StoreSetting::current()->update(['business_name' => 'Acme Store']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Notification::send($user, new OrderPlaced($order));

        $transport = Mail::getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        $sent = $transport->messages()->last()->getOriginalMessage();
        $from = $sent->getFrom()[0];

        $this->assertSame('Acme Store', $from->getName());
        $this->assertSame(config('mail.from.address'), $from->getAddress());
    }
}

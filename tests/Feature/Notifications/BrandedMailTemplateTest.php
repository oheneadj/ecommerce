<?php

/**
 * Covers the shared branded email template (resources/views/vendor/mail)
 * every App\Notifications\*::toMail() renders through — the header/footer
 * business details and the generated brand-color theme CSS.
 */

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BrandedMailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function renderedHtml(): string
    {
        $transport = Mail::getSymfonyTransport();
        $this->assertInstanceOf(ArrayTransport::class, $transport);

        return (string) $transport->messages()->last()->getOriginalMessage()->getHtmlBody();
    }

    public function test_the_header_shows_the_stores_business_name_when_no_logo_is_set(): void
    {
        StoreSetting::current()->update(['business_name' => 'Acme Store', 'logo_path' => null]);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Notification::send($user, new OrderPlaced($order));

        $this->assertStringContainsString('Acme Store', $this->renderedHtml());
        $this->assertStringContainsString('logo-text', $this->renderedHtml());
    }

    public function test_the_header_shows_the_stores_logo_image_when_one_is_set(): void
    {
        StoreSetting::current()->update(['logo_path' => 'branding/logo.png']);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Notification::send($user, new OrderPlaced($order));

        $this->assertStringContainsString('branding/logo.png', $this->renderedHtml());
    }

    public function test_the_footer_shows_business_contact_details(): void
    {
        StoreSetting::current()->update([
            'business_name' => 'Acme Store',
            'tagline' => 'Quality you can trust',
            'contact_email' => 'hello@acme.test',
            'contact_phone' => '0244000000',
            'contact_address' => '12 Ring Road, Accra',
        ]);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Notification::send($user, new OrderPlaced($order));

        $html = $this->renderedHtml();
        $this->assertStringContainsString('Quality you can trust', $html);
        $this->assertStringContainsString('hello@acme.test', $html);
        $this->assertStringContainsString('0244000000', $html);
        $this->assertStringContainsString('12 Ring Road, Accra', $html);
    }

    public function test_the_footer_omits_a_contact_detail_that_is_not_set(): void
    {
        StoreSetting::current()->update(['contact_email' => null, 'contact_phone' => null, 'contact_address' => null]);
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        Notification::send($user, new OrderPlaced($order));

        // No crash/empty-separator artifact from a wholly-unset contact line.
        $this->assertStringNotContainsString(' · <br>', $this->renderedHtml());
    }
}

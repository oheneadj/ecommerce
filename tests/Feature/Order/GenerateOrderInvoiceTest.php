<?php

/**
 * Covers GenerateOrderInvoice — the PDF receipt renderer.
 */

declare(strict_types=1);

namespace Tests\Feature\Order;

use App\Actions\Order\GenerateOrderInvoice;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateOrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_and_stores_a_pdf_file(): void
    {
        Storage::fake('local');
        $order = Order::factory()->create();

        $path = GenerateOrderInvoice::run($order);

        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $order->fresh()->invoice_path);
    }

    /**
     * Regression: the invoice's body previously used the generic CSS
     * `sans-serif` keyword, leaving DomPDF's font resolution ambiguous —
     * the Ghana Cedi Sign (₵) rendered as "?" on the PDF, even though the
     * bundled DejaVu Sans font genuinely has that glyph (confirmed via
     * `fc-query` against the actual .ttf file). Naming the font
     * explicitly is what actually fixes it — this guards against someone
     * reverting to the bare generic keyword later.
     */
    public function test_the_invoice_view_explicitly_names_a_unicode_capable_font(): void
    {
        $this->assertStringContainsString(
            "font-family: 'DejaVu Sans'",
            (string) file_get_contents(resource_path('views/pdf/order-invoice.blade.php')),
        );
    }
}

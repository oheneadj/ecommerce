<?php

/**
 * Covers every admin resource's "Export" bulk action (Customers, Products,
 * Orders, Payments, StockMovements) — all five previously fataled the
 * moment an admin actually clicked "Export" ("Call to a member function
 * getName() on string"), because pxlrbt/filament-excel's withColumns()
 * only accepts Column instances, not plain column-name strings. Also
 * covers the CSV/Excel formula-injection fix on the free-text columns
 * (Customer name, Product name, StockMovement note) those exports include.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_exporting_customers_does_not_error(): void
    {
        $this->actingAs($this->admin());
        $customer = User::factory()->create();

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('export', [$customer]);

        $this->assertTrue(true);
    }

    public function test_exporting_products_does_not_error(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('export', [$product]);

        $this->assertTrue(true);
    }

    public function test_exporting_orders_does_not_error(): void
    {
        $this->actingAs($this->admin());
        $order = Order::factory()->create();

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('export', [$order]);

        $this->assertTrue(true);
    }

    public function test_exporting_payments_does_not_error(): void
    {
        $this->actingAs($this->admin());
        $payment = Payment::factory()->create();

        Livewire::test(ListPayments::class)
            ->callTableBulkAction('export', [$payment]);

        $this->assertTrue(true);
    }

    public function test_exporting_stock_movements_does_not_error(): void
    {
        $this->actingAs($this->admin());
        $movement = StockMovement::factory()->create();

        Livewire::test(ListStockMovements::class)
            ->callTableBulkAction('export', [$movement]);

        $this->assertTrue(true);
    }

    /**
     * The free-text columns these exports include (customer name, product
     * name, stock-movement note) run through SanitizesExportFormulas —
     * confirmed directly by SanitizesExportFormulasTest, and confirmed
     * wired up here so a future edit can't silently drop the call.
     */
    public function test_every_export_with_a_free_text_column_sanitizes_it(): void
    {
        $customersSource = (string) file_get_contents(app_path('Filament/Resources/Customers/Tables/CustomersTable.php'));
        $productsSource = (string) file_get_contents(app_path('Filament/Resources/Products/Tables/ProductsTable.php'));
        $stockMovementsSource = (string) file_get_contents(app_path('Filament/Resources/StockMovements/Tables/StockMovementsTable.php'));

        $this->assertStringContainsString('SanitizesExportFormulas::sanitize', $customersSource);
        $this->assertStringContainsString('SanitizesExportFormulas::sanitize', $productsSource);
        $this->assertStringContainsString('SanitizesExportFormulas::sanitize', $stockMovementsSource);
    }
}

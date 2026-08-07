<?php

/**
 * Covers the Stock Movement create form — quantity must be non-zero, since
 * a zero-quantity movement changes nothing but still writes a meaningless
 * row to the immutable stock_movements ledger.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Filament\Resources\StockMovements\Pages\CreateStockMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockMovementFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_a_zero_quantity_stock_movement_is_rejected_by_the_form(): void
    {
        $this->actingAs($this->admin());
        $variant = ProductVariant::factory()->create();

        Livewire::test(CreateStockMovement::class)
            ->fillForm([
                'product_variant_id' => $variant->id,
                'type' => StockMovementType::Adjustment->value,
                'quantity' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['quantity' => 'not_in']);
    }

    public function test_a_nonzero_stock_movement_is_accepted(): void
    {
        $this->actingAs($this->admin());
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        Livewire::test(CreateStockMovement::class)
            ->fillForm([
                'product_variant_id' => $variant->id,
                'type' => StockMovementType::Restock->value,
                'quantity' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(8, $variant->fresh()->stock);
    }
}

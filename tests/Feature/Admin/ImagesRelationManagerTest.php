<?php

/**
 * Covers Products' ImagesRelationManager — specifically the Scope column,
 * which reads productVariant/attributeTerm off each image row.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImagesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: a discontinued (soft-deleted) variant's image row still
     * needs to render here — this is the admin's own editor for the
     * product these images belong to, not a customer-facing surface — but
     * the Scope column dereferenced productVariant unguarded and crashed
     * once the variant was soft-deleted.
     */
    public function test_an_image_scoped_to_a_soft_deleted_variant_does_not_crash_the_table(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);
        $this->actingAs($user);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'DELETED-SKU']);
        ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'path' => 'products/example.webp',
        ]);
        $variant->delete();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertOk()
            ->assertSee('DELETED-SKU');
    }
}

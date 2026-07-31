<?php

/**
 * Covers the "Generate variants" header action on the Variants tab, which
 * wires the admin panel's form up to GenerateProductVariants.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenerateVariantsActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_generating_variants_from_the_admin_panel_creates_every_combination(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['slug' => 'classic-tee']);
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach([$size->id, $color->id]);

        $l = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'L']);
        $xl = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'XL']);
        $white = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'White']);
        $blue = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Blue']);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('generateVariants', data: [
                'attributeGroups' => [
                    ['attribute_id' => $size->id, 'term_ids' => [$l->id, $xl->id]],
                    ['attribute_id' => $color->id, 'term_ids' => [$white->id, $blue->id]],
                ],
                'sku_prefix' => 'CLASSIC-TEE',
                'price' => 2500,
                'stock' => 5,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(4, $product->variants()->count());
    }
}

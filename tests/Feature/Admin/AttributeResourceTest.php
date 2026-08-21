<?php

/**
 * Covers the global Attribute catalog (Size, Color, Material...) — CRUD via
 * AttributeResource, its Terms relation manager (including the
 * type-driven swatch field), and the policy gate.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AttributeType;
use App\Enums\UserRole;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use App\Filament\Resources\Attributes\RelationManagers\TermsRelationManager;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttributeResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_admin_can_list_attributes(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create(['name' => 'Size']);

        Livewire::test(ListAttributes::class)
            ->assertCanSeeTableRecords([$attribute]);
    }

    public function test_admin_can_create_a_text_attribute(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateAttribute::class)
            ->fillForm([
                'name' => 'Size',
                'slug' => 'size',
                'type' => AttributeType::Text->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('attributes', ['slug' => 'size', 'type' => AttributeType::Text->value]);
    }

    public function test_creating_an_attribute_with_a_name_that_already_exists_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Attribute::factory()->create(['name' => 'Size', 'slug' => 'size']);

        Livewire::test(CreateAttribute::class)
            ->fillForm(['name' => 'Size', 'slug' => 'size-2', 'type' => AttributeType::Text->value])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_store_keeper_can_view_but_not_create_attributes(): void
    {
        $this->actingAs($this->storeKeeper());

        Livewire::test(ListAttributes::class)->assertSuccessful();

        $this->assertFalse($this->storeKeeper()->can('create', Attribute::class));
    }

    /**
     * Regression: `DeleteBulkAction` checked a single batch-wide `deleteAny`
     * ability (absent from `AttributePolicy`, so Filament defaulted to
     * allow) instead of each record's own `delete` ability — a Store
     * Keeper (who legitimately has `viewAny` on this list, but not
     * `delete`, which is Admin/Super Admin only) could bulk-delete
     * attributes straight from the list page.
     */
    public function test_store_keeper_cannot_bulk_delete_attributes(): void
    {
        $this->actingAs($this->storeKeeper());

        $attribute = Attribute::factory()->create();

        Livewire::test(ListAttributes::class)
            ->callTableBulkAction('delete', [$attribute]);

        $this->assertModelExists($attribute);
    }

    /**
     * Regression: deleting an attribute still in use used to just warn and
     * proceed, cascade-deleting its terms and silently stripping the
     * attribute off every variant using it. Now it's blocked entirely.
     */
    public function test_deleting_an_unused_attribute_succeeds(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create();

        Livewire::test(EditAttribute::class, ['record' => $attribute->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($attribute);
    }

    public function test_deleting_an_attribute_assigned_to_a_product_is_blocked(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create();
        $product = Product::factory()->create();
        $attribute->products()->attach($product);

        Livewire::test(EditAttribute::class, ['record' => $attribute->getRouteKey()])
            ->callAction('delete');

        $this->assertModelExists($attribute);
    }

    public function test_deleting_an_attribute_assigned_to_a_variant_is_blocked(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create();
        $term = $attribute->terms()->create(['value' => 'Red', 'slug' => 'red']);
        $variant = ProductVariant::factory()->create();
        $variant->attributeTerms()->attach($term);

        Livewire::test(EditAttribute::class, ['record' => $attribute->getRouteKey()])
            ->callAction('delete');

        $this->assertModelExists($attribute);
    }

    public function test_bulk_deleting_attributes_is_blocked_while_any_selected_one_is_in_use(): void
    {
        $this->actingAs($this->admin());

        $unused = Attribute::factory()->create();
        $inUse = Attribute::factory()->create();
        $product = Product::factory()->create();
        $inUse->products()->attach($product);

        Livewire::test(ListAttributes::class)
            ->callTableBulkAction('delete', [$unused, $inUse]);

        $this->assertModelExists($unused);
        $this->assertModelExists($inUse);
    }

    public function test_admin_can_add_a_text_term_to_an_attribute(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create(['type' => AttributeType::Text]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: ['value' => 'Large', 'slug' => 'large'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('attribute_terms', ['attribute_id' => $attribute->id, 'value' => 'Large']);
    }

    public function test_a_color_attributes_term_requires_a_swatch_value(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create(['type' => AttributeType::Color]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: ['value' => 'Red', 'slug' => 'red'])
            ->assertHasTableActionErrors(['swatch_value' => 'required']);
    }

    public function test_a_color_attributes_term_saves_its_swatch_value(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create(['type' => AttributeType::Color]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: ['value' => 'Red', 'slug' => 'red', 'swatch_value' => '#FF0000'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('attribute_terms', ['attribute_id' => $attribute->id, 'value' => 'Red', 'swatch_value' => '#FF0000']);
    }

    public function test_an_image_attributes_term_saves_its_uploaded_swatch_as_webp(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create(['type' => AttributeType::Image]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: [
                'value' => 'Denim',
                'slug' => 'denim',
                'swatch_value' => UploadedFile::fake()->image('denim.jpg'),
            ])
            ->assertHasNoTableActionErrors();

        $term = $attribute->terms()->sole();
        $this->assertStringEndsWith('.webp', $term->swatch_value);
        Storage::disk('public')->assertExists($term->swatch_value);
    }

    public function test_an_image_attributes_swatch_upload_over_the_configured_size_limit_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        config(['media.max_upload_size_kb' => 100]);

        $attribute = Attribute::factory()->create(['type' => AttributeType::Image]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: [
                'value' => 'Denim',
                'slug' => 'denim',
                'swatch_value' => UploadedFile::fake()->image('denim.jpg')->size(200),
            ])
            // FileUpload::maxSize() registers a Closure rule rather than a
            // plain "max" string, so assert on the key alone.
            ->assertHasTableActionErrors(['swatch_value']);
    }

    public function test_a_terms_value_must_be_unique_within_its_own_attribute(): void
    {
        $this->actingAs($this->admin());

        $attribute = Attribute::factory()->create(['type' => AttributeType::Text]);
        $attribute->terms()->create(['value' => 'Large', 'slug' => 'large']);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: ['value' => 'Large', 'slug' => 'large-2'])
            ->assertHasTableActionErrors(['value' => 'unique']);
    }

    public function test_the_same_term_value_is_allowed_under_a_different_attribute(): void
    {
        $this->actingAs($this->admin());

        $size = Attribute::factory()->create(['type' => AttributeType::Text]);
        $size->terms()->create(['value' => 'Large', 'slug' => 'large']);
        $color = Attribute::factory()->create(['type' => AttributeType::Text]);

        Livewire::test(TermsRelationManager::class, ['ownerRecord' => $color, 'pageClass' => EditAttribute::class])
            ->callTableAction('create', data: ['value' => 'Large', 'slug' => 'large'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('attribute_terms', ['attribute_id' => $color->id, 'value' => 'Large']);
    }
}

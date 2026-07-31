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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

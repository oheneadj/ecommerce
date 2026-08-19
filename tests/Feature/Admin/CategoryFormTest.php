<?php

/**
 * Covers the Category admin form's parent-category selection — bug hunt
 * finding: nothing previously prevented a category from being set as its
 * own parent, or as a descendant of itself, either of which creates a
 * cycle nothing else in the schema or DB guards against.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryFormTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_a_category_cannot_be_set_as_its_own_parent(): void
    {
        $category = Category::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['parent_id' => $category->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_a_category_cannot_be_set_as_a_descendant_of_itself(): void
    {
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->create(['parent_id' => $grandparent->id]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $this->actingAs($this->admin());

        // Setting the grandparent's parent to its own grandchild would
        // create a cycle: grandparent -> parent -> child -> grandparent.
        Livewire::test(EditCategory::class, ['record' => $grandparent->getRouteKey()])
            ->fillForm(['parent_id' => $child->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);

        $this->assertNull($grandparent->fresh()->parent_id);
    }

    public function test_a_category_can_be_set_as_a_parent_of_an_unrelated_category(): void
    {
        $parent = Category::factory()->create();
        $other = Category::factory()->create();
        $this->actingAs($this->admin());

        Livewire::test(EditCategory::class, ['record' => $other->getRouteKey()])
            ->fillForm(['parent_id' => $parent->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($parent->id, $other->fresh()->parent_id);
    }

    public function test_descendant_ids_covers_every_depth_not_just_direct_children(): void
    {
        $grandparent = Category::factory()->create();
        $parent = Category::factory()->create(['parent_id' => $grandparent->id]);
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $ids = $grandparent->descendantIds();

        $this->assertContains($parent->id, $ids);
        $this->assertContains($child->id, $ids);
    }
}

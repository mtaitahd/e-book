<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => 'Data Science',
                'description' => 'Analytics and statistics titles.',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Category created successfully.');

        $this->assertDatabaseHas('categories', [
            'name' => 'Data Science',
            'slug' => 'data-science',
            'description' => 'Analytics and statistics titles.',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_edit_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Category', 'slug' => 'old-category']);

        $this->actingAs($this->admin())
            ->put(route('admin.categories.update', $category), [
                'name' => 'Refreshed Category',
                'description' => 'Now with more.',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Category updated successfully.');

        $category->refresh();

        $this->assertSame('Refreshed Category', $category->name);
        $this->assertSame('refreshed-category', $category->slug);
        $this->assertFalse($category->isActive());
    }

    public function test_category_validation_works(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), [
                'name' => '',
                'status' => 'not-a-status',
            ])
            ->assertSessionHasErrors(['name', 'status']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_duplicate_category_names_get_unique_slugs(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'History', 'status' => 'active'])
            ->assertRedirect(route('admin.categories.index'));

        $this->actingAs($this->admin())
            ->post(route('admin.categories.store'), ['name' => 'History', 'status' => 'active'])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['slug' => 'history']);
        $this->assertDatabaseHas('categories', ['slug' => 'history-2']);
    }

    public function test_admin_can_deactivate_category_and_relationships_survive(): void
    {
        $category = Category::factory()->create(['name' => 'Keep Category']);
        $book = \App\Models\Book::factory()->published()->create();
        $book->categories()->attach($category);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Category archived successfully.');

        $category->refresh();
        $this->assertFalse($category->isActive());
        $this->assertTrue($book->categories()->where('categories.id', $category->id)->exists());
    }

    public function test_admin_can_restore_archived_category(): void
    {
        $category = Category::factory()->create(['name' => 'Back in Stock', 'status' => Category::STATUS_INACTIVE]);

        $this->actingAs($this->admin())
            ->patch(route('admin.categories.restore', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Category restored successfully.');

        $this->assertTrue($category->fresh()->isActive());
    }

    public function test_index_lists_restore_for_archived_and_delete_for_active_categories(): void
    {
        $active = Category::factory()->create(['name' => 'Active One']);
        $archived = Category::factory()->create(['name' => 'Archived One', 'status' => Category::STATUS_INACTIVE]);

        $this->actingAs($this->admin())
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee(route('admin.categories.destroy', $active), false)
            ->assertSee(route('admin.categories.force-destroy', $active), false)
            ->assertSee(route('admin.categories.restore', $archived), false)
            ->assertSee(route('admin.categories.force-destroy', $archived), false);
    }

    public function test_admin_can_permanently_delete_an_unused_category(): void
    {
        $category = Category::factory()->create(['name' => 'Gone Forever']);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.force-destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success', 'Category permanently deleted.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_cannot_be_permanently_deleted_while_still_linked_to_a_book(): void
    {
        $category = Category::factory()->create(['name' => 'Still Used']);
        $book = \App\Models\Book::factory()->published()->create();
        $book->categories()->attach($category);

        $this->actingAs($this->admin())
            ->delete(route('admin.categories.force-destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertTrue($book->categories()->where('categories.id', $category->id)->exists());
    }

    public function test_customer_cannot_manage_categories(): void
    {
        $customer = User::factory()->customer()->create();
        $category = Category::factory()->create();

        $this->actingAs($customer)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.categories.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.categories.store'), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.categories.restore', $category))->assertForbidden();
        $this->actingAs($customer)->delete(route('admin.categories.force-destroy', $category))->assertForbidden();
    }
}
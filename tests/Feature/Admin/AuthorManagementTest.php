<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_author(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), [
                'name' => 'Jane Wanjiku',
                'bio' => 'A curious writer.',
            ])
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('success', 'Author created successfully.');

        $this->assertDatabaseHas('authors', [
            'name' => 'Jane Wanjiku',
            'slug' => 'jane-wanjiku',
            'bio' => 'A curious writer.',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_edit_author(): void
    {
        $author = Author::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->actingAs($this->admin())
            ->put(route('admin.authors.update', $author), [
                'name' => 'New Author Name',
                'bio' => 'Updated bio.',
            ])
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('success', 'Author updated successfully.');

        $author->refresh();

        $this->assertSame('New Author Name', $author->name);
        $this->assertSame('new-author-name', $author->slug);
        $this->assertSame('Updated bio.', $author->bio);
    }

    public function test_author_validation_works(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), ['name' => '', 'bio' => null])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('authors', 0);
    }

    public function test_duplicate_author_names_get_unique_slugs(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), ['name' => 'Alex Kim'])
            ->assertRedirect(route('admin.authors.index'));

        $this->actingAs($this->admin())
            ->post(route('admin.authors.store'), ['name' => 'Alex Kim'])
            ->assertRedirect(route('admin.authors.index'));

        $this->assertDatabaseHas('authors', ['slug' => 'alex-kim']);
        $this->assertDatabaseHas('authors', ['slug' => 'alex-kim-2']);
    }

    public function test_admin_can_archive_author_and_relationships_survive(): void
    {
        $author = Author::factory()->create(['name' => 'Keep Me']);
        $book = \App\Models\Book::factory()->published()->create();
        $book->authors()->attach($author);

        $this->actingAs($this->admin())
            ->delete(route('admin.authors.destroy', $author))
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('success', 'Author archived successfully.');

        $author->refresh();
        $this->assertFalse($author->isActive());
        $this->assertTrue($book->authors()->where('authors.id', $author->id)->exists());
    }

    public function test_admin_can_restore_archived_author(): void
    {
        $author = Author::factory()->create(['name' => 'Back Again', 'status' => Author::STATUS_INACTIVE]);

        $this->actingAs($this->admin())
            ->patch(route('admin.authors.restore', $author))
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('success', 'Author restored successfully.');

        $this->assertTrue($author->fresh()->isActive());
    }

    public function test_index_lists_restore_for_archived_and_delete_for_active_authors(): void
    {
        $active = Author::factory()->create(['name' => 'Active One']);
        $archived = Author::factory()->create(['name' => 'Archived One', 'status' => Author::STATUS_INACTIVE]);

        $this->actingAs($this->admin())
            ->get(route('admin.authors.index'))
            ->assertOk()
            ->assertSee(route('admin.authors.destroy', $active), false)
            ->assertSee(route('admin.authors.force-destroy', $active), false)
            ->assertSee(route('admin.authors.restore', $archived), false)
            ->assertSee(route('admin.authors.force-destroy', $archived), false);
    }

    public function test_admin_can_permanently_delete_an_unused_author(): void
    {
        $author = Author::factory()->create(['name' => 'Gone Forever']);

        $this->actingAs($this->admin())
            ->delete(route('admin.authors.force-destroy', $author))
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('success', 'Author permanently deleted.');

        $this->assertDatabaseMissing('authors', ['id' => $author->id]);
    }

    public function test_author_cannot_be_permanently_deleted_while_still_credited_on_a_book(): void
    {
        $author = Author::factory()->create(['name' => 'Still Writing']);
        $book = \App\Models\Book::factory()->published()->create();
        $book->authors()->attach($author);

        $this->actingAs($this->admin())
            ->delete(route('admin.authors.force-destroy', $author))
            ->assertRedirect(route('admin.authors.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('authors', ['id' => $author->id]);
        $this->assertTrue($book->authors()->where('authors.id', $author->id)->exists());
    }

    public function test_customer_cannot_manage_authors(): void
    {
        $customer = User::factory()->customer()->create();
        $author = Author::factory()->create();

        $this->actingAs($customer)->get(route('admin.authors.index'))->assertForbidden();
        $this->actingAs($customer)->get(route('admin.authors.create'))->assertForbidden();
        $this->actingAs($customer)->post(route('admin.authors.store'), ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($customer)->patch(route('admin.authors.restore', $author))->assertForbidden();
        $this->actingAs($customer)->delete(route('admin.authors.force-destroy', $author))->assertForbidden();
    }
}
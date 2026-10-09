<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_author_relationship_works_through_admin_create(): void
    {
        $admin = User::factory()->admin()->create();
        $authorA = Author::factory()->create();
        $authorB = Author::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Two Author Book',
                'price' => '1200',
                'author_ids' => [$authorA->id, $authorB->id],
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.books.index'));

        $book = Book::where('slug', 'two-author-book')->first();

        $this->assertTrue($book->authors->contains('id', $authorA->id));
        $this->assertTrue($book->authors->contains('id', $authorB->id));
        $this->assertCount(2, $book->authors);
    }

    public function test_book_category_relationship_works_through_admin_create(): void
    {
        $admin = User::factory()->admin()->create();
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.books.store'), [
                'title' => 'Multi Category Book',
                'price' => '800',
                'author_ids' => [Author::factory()->create()->id],
                'category_ids' => [$categoryA->id, $categoryB->id],
                'status' => 'published',
            ])
            ->assertRedirect(route('admin.books.index'));

        $book = Book::where('slug', 'multi-category-book')->first();

        $this->assertTrue($book->categories->contains('id', $categoryA->id));
        $this->assertTrue($book->categories->contains('id', $categoryB->id));
        $this->assertCount(2, $book->categories);
    }

    public function test_dashboard_catalog_counts_reflect_actual_db_records(): void
    {
        Book::factory()->published()->count(4)->create();
        Book::factory()->count(2)->create();
        Book::factory()->archived()->create();
        Author::factory()->count(3)->create();
        Category::factory()->count(2)->create();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();

        $response->assertViewHas('stats', [
            'books' => 7,
            'published_books' => 4,
            'draft_books' => 2,
            'categories' => 2,
            'authors' => 3,
            'orders' => 0,
            'pending_orders' => 0,
            'paid_orders' => 0,
        ]);
    }

    public function test_dashboard_does_not_show_fabricated_sales_numbers(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();

        // The dashboard only ever reports real order counts. It must not claim
        // a revenue or sales figure it has not calculated, now that the sales
        // card has moved into dedicated pages.
        $response->assertDontSee('Revenue');
        $response->assertDontSee('revenue');
        $response->assertDontSee('Turnover');
        $response->assertDontSee('TZS ');

        $this->assertArrayNotHasKey('revenue', $response->viewData('stats'));
        $this->assertArrayNotHasKey('sales', $response->viewData('stats'));
        $this->assertArrayHasKey('orders', $response->viewData('stats'));
    }
}

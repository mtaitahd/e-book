<?php

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontChromeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_home_page_no_longer_repeats_the_category_list(): void
    {
        // Categories now live in the header dropdown, so the home page must not
        // repeat them as a strip of count tiles.
        Category::factory()->create(['name' => 'Programming', 'status' => Category::STATUS_ACTIVE]);
        Book::factory()->published()->create(['title' => 'Latest One']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="category-strip"', false)
            ->assertDontSee('class="category-tile"', false);

        // The book sections themselves are untouched.
        $this->get(route('home'))->assertSee('Latest Published Books');
    }

    public function test_the_top_nav_lists_every_active_category(): void
    {
        $category = Category::factory()->create([
            'name' => 'Business',
            'status' => Category::STATUS_ACTIVE,
        ]);

        $hidden = Category::factory()->create([
            'name' => 'Retired Shelf',
            'status' => Category::STATUS_INACTIVE,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="ebs-topnav__menu"', false)
            ->assertSee(route('categories.show', $category), false)
            ->assertSee('Business')
            ->assertDontSee('Retired Shelf');
        $this->assertNotSame(route('categories.show', $hidden), route('categories.show', $category));
    }

    public function test_the_categories_toggle_still_opens_the_drawer(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-drawer-open="ebsDrawer"', false)
            ->assertSee('id="ebsDrawer"', false);
    }

    public function test_the_books_storefront_lists_published_books_without_a_hero(): void
    {
        Book::factory()->published()->create(['title' => 'A Listed Book']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('A Listed Book')
            ->assertDontSee('books-hero', false);
    }

    public function test_the_filter_bar_does_not_link_to_a_removed_home_section(): void
    {
        // The home page no longer has a #categories section, so the filter bar's
        // Categories pill must point at a real route instead of a dead anchor.
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('#categories', $html);
        $this->assertStringContainsString(route('books.index'), $html);
    }

    public function test_the_home_page_has_no_landing_hero(): void
    {
        // The landing hero was removed; the "Read. Learn. Grow." band and its
        // call to action must not reappear above the book grid. ("Browse Books"
        // is deliberately not checked here: the site header links to the same
        // route legitimately, so its presence proves nothing about the hero.)
        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('home-hero', false)
            ->assertDontSee('--home-hero-image', false)
            ->assertDontSee('Read. Learn. Grow.')
            ->assertDontSee('instant access the moment your payment clears', false);
    }

    public function test_the_home_page_carries_the_artwork_background_class(): void
    {
        Book::factory()->published()->create(['title' => 'Latest One']);

        $html = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Latest Published Books')
            ->getContent();

        // The artwork is applied to <main>, so it covers the whole home page
        // rather than any single section. The path itself lives in the
        // stylesheet (two levels up from /assets/storefront/css/).
        $this->assertStringContainsString('site-main--art', $html);
        $this->assertStringNotContainsString('home-latest', $html);
        $this->assertStringNotContainsString('home-showcase', $html);
        $this->assertStringNotContainsString('--home-latest-image', $html);

        $this->assertFileExists(public_path('assets/book1.jpg'));

        // The home background is the landscape artwork, not the old square logo.
        $css = (string) file_get_contents(public_path('assets/storefront/css/app.css'));
        $artwork = $this->artworkRule($css);

        $this->assertNotNull($artwork, 'The site-main--art::before rule is missing.');
        $this->assertStringContainsString('../../book1.jpg', $artwork);
        $this->assertStringNotContainsString('ebook.png', $artwork);

        // The artwork is veiled by a translucent white sheet rather than being
        // faded directly, so the image strength and the amount of page showing
        // through it stay independently adjustable.
        $this->assertSame(
            1,
            preg_match('/\.site-main--art::after\s*\{(.*?)\}/s', $css, $veil),
            'The site-main--art::after veil rule is missing.'
        );

        $this->assertMatchesRegularExpression(
            '/background-color:\s*#fff/i',
            $veil[1],
            'The veil should be a white sheet.'
        );
        $this->assertMatchesRegularExpression(
            '/opacity:\s*\.\d+/',
            $veil[1],
            'The veil should be translucent, not fully opaque.'
        );

        // The artwork has to sit behind the veil, or the veil covers it up.
        $this->assertStringContainsString('z-index: -2', $artwork);
        $this->assertStringContainsString('z-index: -1', $veil[1]);
    }

    /**
     * Pull the body of the `.site-main--art::before` rule out of the stylesheet.
     */
    private function artworkRule(string $css): ?string
    {
        if (! preg_match('/\.site-main--art::before\s*\{(.*?)\}/s', $css, $m)) {
            return null;
        }

        return $m[1];
    }

    public function test_other_storefront_pages_do_not_carry_the_artwork_background(): void
    {
        // The artwork is a home-page treatment; it must not leak onto the
        // catalogue, category, book, cart or account pages.
        foreach ([
            'books' => route('books.index'),
            'cart' => route('cart.show'),
        ] as $label => $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee('site-main--art', false, "The {$label} page should not carry the home artwork.");
        }
    }

    public function test_the_books_listing_no_longer_has_the_all_books_hero(): void
    {
        $html = $this->get(route('books.index'))
            ->assertOk()
            ->assertDontSee('books-hero', false)
            ->assertDontSee('Browse the full catalogue of published books.')
            // "All Books" itself is not asserted: it is also a filter-bar pill,
            // so its presence says nothing about the hero.
            ->assertDontSee('books-results-line', false)
            ->getContent();

        // The hero is gone, but search still reports what it found.
        $this->get(route('books.index', ['q' => 'worker']))
            ->assertOk()
            ->assertSee('books-results-line', false);
    }

    public function test_a_real_cover_image_is_still_rendered_on_its_card(): void
    {
        $book = Book::factory()->published()->create(['cover_image' => 'covers/book.jpg']);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('covers/book.jpg');
    }
}

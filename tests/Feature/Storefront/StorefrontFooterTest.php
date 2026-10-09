<?php

namespace Tests\Feature\Storefront;

use App\Models\Book;
use App\Models\Category;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontFooterTest extends TestCase
{
    use RefreshDatabase;

    private function publishedBook(): Book
    {
        return Book::factory()->published()->create();
    }

    private function activeCategory(): Category
    {
        return Category::factory()->create(['name' => 'Programming', 'status' => Category::STATUS_ACTIVE]);
    }

    public function test_the_footer_renders_on_every_public_storefront_page(): void
    {
        $category = $this->activeCategory();
        $book = $this->publishedBook();

        $pages = [
            'home'          => route('home'),
            'books'         => route('books.index'),
            'category'      => route('categories.show', $category),
            'book detail'   => route('books.show', $book),
            'cart'          => route('cart.show'),
        ];

        foreach ($pages as $label => $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('ebs-footer', false)
                ->assertSee('ebs-footer-cols', false)
                ->assertSee('ebs-footer-bottom', false)
                ->assertDontSee('ebs-footer-news', false);
        }
    }

    public function test_the_footer_has_the_required_two_sections(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Section A: the four columns, in order.
        $this->assertStringContainsString('Read &bull; Learn &bull; Grow', $html);
        foreach (['Explore', 'Help &amp; Support', 'Contact Us'] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }

        // Section B: dynamic copyright year (rendered as the &copy; entity).
        $this->assertStringContainsString('&copy; '.now()->year.' E-Book. All rights reserved.', $html);
    }

    public function test_the_footer_no_longer_contains_the_newsletter(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match('/<footer\b.*?<\/footer>/s', $html, $m);
        $this->assertNotEmpty($m[0], 'No <footer> element was rendered.');
        $footer = $m[0];

        // The newsletter moved out of the footer entirely; subscribing is an
        // admin action now, so none of this copy or form may survive here.
        foreach ([
            'Stay Updated with New E-Books',
            'Get the latest books, offers and reading tips',
            'Your email address',
            'No spam. Unsubscribe anytime.',
            'ebs-footer-news',
            'ebs-footer-news__form',
            'ebs-footer-news__input',
            'ebs-footer-news__btn',
            'ebs-footer-news__art',
            'ebsFooterEmail',
            'ebsFooterNewsTitle',
            'ebsFooterNewsNote',
            'ebs-sr-only',
        ] as $gone) {
            $this->assertStringNotContainsString($gone, $footer, "Footer still contains '{$gone}'.");
        }

        // No email input, and no form, is left anywhere in the footer.
        $this->assertStringNotContainsString('type="email"', $footer);
        $this->assertStringNotContainsString('<form', $footer);

        // The decorative book artwork only ever appeared in the newsletter.
        $this->assertStringNotContainsString(asset('assets/ebook.png'), $footer);
    }

    public function test_the_footer_only_links_to_routes_that_actually_exist(): void
    {
        $this->activeCategory();
        $this->publishedBook();

        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/<footer\b.*?<\/footer>/s', $html, $blocks);
        $this->assertNotEmpty($blocks[0], 'No <footer> element was rendered.');

        preg_match_all('/href="([^"]*)"/', $blocks[0][0], $hrefs);

        // Every destination the footer is allowed to point at. Anything outside
        // this list would be an invented URL.
        $allowed = [
            'mailto:support@ebook.example',
            'tel:+255000000000',
        ];
        foreach (['home', 'books.index', 'cart.show', 'login'] as $name) {
            $allowed[] = route($name);
        }
        $allowed[] = route('categories.show', Category::first());

        $internal = [];
        foreach ($hrefs[1] as $href) {
            if ($href === '#') {
                // Inert social placeholders, deliberately marked in the view.
                continue;
            }
            $this->assertContains($href, $allowed, "Footer links to an unexpected URL: {$href}");

            if (str_starts_with($href, rtrim(url('/'), '/').'/')) {
                $internal[] = $href;
            }
        }

        $this->assertNotEmpty($internal, 'Footer should contain real internal navigation links.');
    }

    public function test_the_footer_shows_only_the_mobile_money_methods_that_are_supported(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (Payment::NETWORKS as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertSame(4, substr_count($html, 'ebs-footer-pay__logo'));

        // There is no card payment in this application, so no card brand may
        // be advertised in the footer.
        foreach (['Visa', 'Mastercard', 'MasterCard', 'PayPal', 'card payment'] as $brand) {
            $this->assertStringNotContainsString($brand, $html);
        }
    }

    public function test_the_footer_is_accessible(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Semantic landmark.
        $this->assertStringContainsString('<footer', $html);

        // Column headings are referenced by the sections they label.
        foreach (['ebsFooterExploreTitle', 'ebsFooterHelpTitle', 'ebsFooterContactTitle'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }

        // Icon-only social links are named, not left as bare icons.
        foreach (['Facebook', 'X', 'Instagram', 'YouTube', 'LinkedIn'] as $network) {
            $this->assertStringContainsString('aria-label="E-Book on '.$network.'"', $html);
        }

        // Payment badges are exposed as a labelled group.
        $this->assertStringContainsString('aria-label="Accepted payment methods"', $html);
    }

    public function test_the_footer_renders_the_simplified_column_set_for_signed_in_customers(): void
    {
        // Guest assertions run first: actingAs() is sticky for the rest of the
        // test, so a "guest" request after it would still be authenticated.
        $guest = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('account.show'), $guest);
        $this->assertStringContainsString('data-ebs-auth-open="login"', $guest);

        $user = \App\Models\User::factory()->create();

        $html = $this->actingAs($user)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('account.show'), $html);
        $this->assertStringContainsString(route('account.orders.index'), $html);
        $this->assertStringContainsString(route('account.purchases.index'), $html);
    }

    public function test_the_footer_uses_the_named_background_asset(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // footer.png remains the footer's background artwork, and the old
        // ebook.png logo is no longer referenced anywhere inside the footer
        // itself. (The home backdrop is a separate asset, book1.jpg, so
        // this has to be scoped to the <footer> block rather than the whole
        // document.)
        preg_match('/<footer\b.*?<\/footer>/s', $html, $m);
        $this->assertNotEmpty($m[0], 'No <footer> element was rendered.');

        $this->assertStringContainsString('--ebs-footer-bg-image: url(\''.asset('assets/footer.png').'\')', $m[0]);
        $this->assertStringNotContainsString('--ebs-footer-news-image', $m[0]);
        $this->assertStringNotContainsString('ebook.png', $m[0]);

        $this->assertFileExists(public_path('assets/footer.png'));
    }

    public function test_authentication_pages_are_unaffected_by_the_storefront_footer(): void
    {
        // The login page uses the separate auth layout, so the storefront
        // footer must not leak into it.
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('ebs-footer', false);
    }

    public function test_the_customer_area_keeps_its_existing_footer(): void
    {
        // The account area is deliberately left alone: it must not inherit the
        // storefront footer, and the reader still hides the footer it has.
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->get(route('account.show'))
            ->assertOk()
            ->assertDontSee('ebs-footer', false)
            ->assertSee('class="site-footer"', false);
    }
}

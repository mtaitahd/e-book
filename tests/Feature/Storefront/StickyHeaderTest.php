<?php

namespace Tests\Feature\Storefront;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StickyHeaderTest extends TestCase
{
    use RefreshDatabase;

    private function css(): string
    {
        $path = public_path('assets/storefront/css/app.css');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    private function cssBlock(string $selector): string
    {
        $css = $this->css();
        $position = strpos($css, $selector);
        $this->assertNotFalse($position, "Missing CSS block for {$selector}");

        $open = strpos($css, '{', $position);
        $close = strpos($css, '}', $open);

        return substr($css, $open + 1, $close - $open - 1);
    }

    public function test_header_contributes_no_box_so_the_topbar_can_reach_the_viewport(): void
    {
        // Sticky is clamped to the parent's box, so the header must not have one.
        $this->assertStringContainsString('display: contents', $this->cssBlock('.ebs-header {'));
    }

    public function test_only_the_dark_topbar_is_sticky(): void
    {
        $topbar = $this->cssBlock('.ebs-topbar {');

        $this->assertStringContainsString('position: sticky', $topbar);
        $this->assertStringContainsString('top: 0', $topbar);
        $this->assertStringContainsString('z-index: 60', $topbar);
    }

    public function test_the_secondary_books_bar_has_been_removed(): void
    {
        // The white "Books / Shop Books / Categories / Your Books" bar duplicated
        // the sticky topbar directly above it, so it is gone along with its CSS.
        $this->assertStringNotContainsString('ebs-booksnav', $this->css());

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('ebs-booksnav', $html);
        $this->assertStringNotContainsString('Shop Books', $html);
    }

    public function test_the_categories_toggle_lives_in_the_sticky_topbar(): void
    {
        // The bar that used to hold the drawer toggle is gone, so the toggle that
        // opens the categories drawer has to be reachable from the sticky topbar.
        $this->assertStringNotContainsString('.ebs-subnav', $this->css());

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('class="ebs-subnav"', false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="ebs-topnav"', false)
            ->assertSee('data-drawer-open="ebsDrawer"', false);
    }

    public function test_the_sticky_bar_is_opaque_so_content_never_shows_through(): void
    {
        $this->assertStringContainsString('background: var(--ebs-navy)', $this->cssBlock('.ebs-topbar {'));
    }

    public function test_the_body_does_not_clip_the_sticky_bar(): void
    {
        // overflow:hidden on an ancestor would silently kill position:sticky.
        $this->assertStringNotContainsString('overflow: hidden', $this->cssBlock('body {'));
    }

    public function test_dropdowns_still_stack_above_the_sticky_bar(): void
    {
        // The topbar is now its own stacking context, so the menus inside it
        // must keep a higher z-index than the bar itself.
        $topbar = $this->cssBlock('.ebs-topbar {');
        $this->assertSame(60, (int) preg_match('/z-index:\s*(\d+)/', $topbar, $m) ? (int) $m[1] : 0);

        foreach (['.ebs-search__cat-menu {', '.ebs-account__panel {'] as $menu) {
            $this->assertGreaterThan(
                60,
                (int) preg_match('/z-index:\s*(\d+)/', $this->cssBlock($menu), $match) ? (int) $match[1] : 0,
                "{$menu} must paint above the sticky topbar"
            );
        }
    }

    public function test_the_header_markup_wraps_the_remaining_bar(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<header class="ebs-header">', false)
            ->assertSee('class="ebs-topbar"', false)
            // The storefront header is now a single bar.
            ->assertDontSee('class="ebs-booksnav"', false);
    }

    public function test_the_sticky_topbar_keeps_its_navigation(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="ebs-logo"', false)
            ->assertSee('class="ebs-topnav"', false)
            ->assertSee('id="ebsSearchInput"', false)
            ->assertSee('class="ebs-cart"', false)
            ->assertSee('class="ebs-account"', false);
    }
}

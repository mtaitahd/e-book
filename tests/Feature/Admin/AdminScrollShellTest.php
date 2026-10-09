<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin is a fixed-height shell: the page does not scroll, the sidebar and
 * the topbar sit outside the one scroll container, and only the content moves.
 *
 * This is regression cover for a bug that CSS assertions alone would not have
 * caught. Pinning the topbar with position:sticky looked correct but did
 * nothing, because the template's `overflow-x: hidden` on #content-wrapper
 * quietly turned that element into a scroll container, and a sticky element only
 * moves relative to a *scrolling* ancestor. The bar scrolled away regardless.
 */
class AdminScrollShellTest extends TestCase
{
    use RefreshDatabase;

    private function css(): string
    {
        $path = public_path('assets/admin/css/style.css');
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
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

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_shell_is_locked_to_the_viewport(): void
    {
        // If the page itself can scroll, the sidebar and the topbar scroll with
        // it no matter what else the stylesheet says.
        $this->assertStringContainsString('height: 100vh', $this->cssBlock('#wrapper {'));
        $this->assertStringContainsString('overflow: hidden', $this->cssBlock('#wrapper {'));

        $this->assertStringContainsString('height: 100vh', $this->cssBlock('#content-wrapper {'));
        $this->assertStringContainsString('overflow: hidden', $this->cssBlock('#content-wrapper {'));
    }

    public function test_the_sidebar_fills_the_viewport_and_scrolls_its_own_menu(): void
    {
        $sidebar = $this->cssBlock('.sidebar {');

        $this->assertStringContainsString('height: 100vh', $sidebar);
        // A short admin menu scrolls inside the bar; a long one must not push the
        // shell taller than the window.
        $this->assertStringContainsString('overflow-y: auto', $sidebar);
    }

    public function test_the_content_is_a_column_so_the_scroll_area_can_take_the_slack(): void
    {
        $content = $this->cssBlock('#content {');

        $this->assertStringContainsString('display: flex', $content);
        $this->assertStringContainsString('flex-direction: column', $content);
        // Without this the flex item refuses to shrink below its content height
        // and the shell grows past the viewport instead of scrolling.
        $this->assertStringContainsString('min-height: 0', $content);
    }

    public function test_admin_scroll_is_the_only_scroll_container(): void
    {
        $scroll = $this->cssBlock('#adminScroll {');

        $this->assertStringContainsString('flex: 1 1 auto', $scroll);
        $this->assertStringContainsString('min-height: 0', $scroll);
        $this->assertStringContainsString('overflow-y: auto', $scroll);
    }

    public function test_the_template_overflow_no_longer_defeats_the_shell(): void
    {
        // #content-wrapper is now locked by the shell, so the template's own
        // `overflow-x: hidden` can no longer turn it into a scroll container --
        // which is what silently killed the previous sticky attempt.
        $this->assertStringNotContainsString('overflow-x: clip', $this->css());
    }

    public function test_the_shell_is_flattened_for_printing(): void
    {
        // A clipped, scrolling shell would print as a single truncated page.
        $css = $this->css();
        $this->assertStringContainsString('@media print', $css);

        // The print override must flatten the key shell elements back to normal
        // document flow.
        $this->assertMatchesRegularExpression('/@media\s+print\s*\{[\s\S]*#adminScroll[\s\S]*\}/', $css);
    }

    public function test_the_layout_puts_the_scroll_container_below_the_topbar(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="adminScroll"', $html);

        // Order matters: the topbar and the sidebar both have to come before the
        // scroll container, or they would scroll away with the content.
        $topbar = strpos($html, 'class="navbar navbar-expand navbar-light bg-navbar topbar');
        $sidebar = strpos($html, 'class="sidebar');
        $scroll = strpos($html, 'id="adminScroll"');

        $this->assertNotFalse($topbar, 'The topbar is missing from the admin layout.');
        $this->assertNotFalse($sidebar, 'The sidebar is missing from the admin layout.');
        $this->assertNotFalse($scroll, 'The admin scroll container is missing.');

        $this->assertLessThan($scroll, $topbar, 'The topbar must sit above the scroll container.');
        $this->assertLessThan($scroll, $sidebar, 'The sidebar must sit outside the scroll container.');
    }

    public function test_the_topbar_does_not_rely_on_sticky_positioning(): void
    {
        // It is above the scroll container now, so sticky is only there for the
        // dropdown's z-index. It must not be the thing holding the bar in place.
        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('id="adminScroll"', false);
    }

    public function test_the_footer_is_gone(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('admin-footer', $html);
        $this->assertStringNotContainsString('developed by', $html);
    }
}

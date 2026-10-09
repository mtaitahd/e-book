<?php

namespace Tests\Feature\Reader;

use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Purchase\PurchaseTestCase;

class ReaderLayoutTest extends PurchaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdfPurchase(User $user): Purchase
    {
        $book = $this->publishedBook([
            'title' => 'Layout Book',
            'file_type' => 'pdf',
        ]);
        $book->update(['file_path' => 'ebooks/layout.pdf']);
        Storage::disk('local')->put('ebooks/layout.pdf', "%PDF-1.4\nreader content\n%%EOF\n");

        return $this->paidOrder($user, [['book' => $book]])->purchases()->firstOrFail();
    }

    private function openReader(): TestResponse
    {
        $purchase = $this->pdfPurchase($this->customer());

        return $this->actingAs($purchase->user)
            ->get(route('account.purchases.read', $purchase))
            ->assertOk();
    }

    private function cssBlock(string $html, string $selector): string
    {
        $position = strpos($html, $selector);
        $this->assertNotFalse($position, "Missing CSS block for {$selector}");

        $open = strpos($html, '{', $position);
        $close = strpos($html, '}', $open);

        return substr($html, $open + 1, $close - $open - 1);
    }

    private function mobileBlock(string $html): string
    {
        $start = strpos($html, '@media (max-width: 768px)');
        $this->assertNotFalse($start, 'Missing the mobile breakpoint');

        $open = strpos($html, '{', $start);
        $depth = 0;
        $length = strlen($html);

        for ($i = $open; $i < $length; $i++) {
            if ($html[$i] === '{') {
                $depth++;
            } elseif ($html[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($html, $open, $i - $open + 1);
                }
            }
        }

        $this->fail('Unbalanced braces in the mobile breakpoint.');
    }

    public function test_reader_page_hides_the_whole_site_navigation(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString('<body class="page-reader">', $html);
        $this->assertStringContainsString('.page-reader .ebs-header', $html);
        $this->assertStringContainsString('.page-reader .site-footer { display: none; }', $html);
    }

    public function test_reader_page_locks_the_body_scrollbar(): void
    {
        $this->openReader()
            ->assertSee('body.page-reader { overflow: hidden; }', false);
    }

    public function test_reader_page_neutralises_the_page_shell_padding(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString('.page-reader .site-main { padding: 0; }', $html);
        $this->assertStringContainsString('.page-reader .container { max-width: none; padding: 0; }', $html);
    }

    public function test_reader_fills_the_whole_viewport(): void
    {
        $reader = $this->cssBlock($this->openReader()->getContent(), '.reader {');

        $this->assertStringContainsString('height: 100dvh', $reader);
        $this->assertStringContainsString('flex-direction: column', $reader);
    }

    public function test_reader_never_creates_a_containing_block_for_the_fixed_toolbar(): void
    {
        $reader = $this->cssBlock($this->openReader()->getContent(), '.reader {');

        // transform/filter/backdrop-filter would re-anchor the fixed toolbar
        // to the reader instead of the viewport.
        $this->assertStringNotContainsString('transform', $reader);
        $this->assertStringNotContainsString('filter', $reader);
    }

    public function test_top_bar_carries_only_back_title_and_expand(): void
    {
        $html = $this->openReader()->getContent();

        $head = $this->cssBlock($html, '.reader-head {');
        $this->assertStringContainsString('grid-template-columns: 1fr auto 1fr', $head);

        $this->assertStringContainsString('justify-self: start', $this->cssBlock($html, '.reader-back {'));
        $this->assertStringContainsString('justify-self: center', $this->cssBlock($html, '.reader-title {'));
        $this->assertStringContainsString('justify-self: end', $this->cssBlock($html, '.reader-expand {'));
    }

    public function test_top_bar_back_link_returns_to_the_library(): void
    {
        $purchase = $this->pdfPurchase($this->customer());

        $this->actingAs($purchase->user)
            ->get(route('account.purchases.read', $purchase))
            ->assertOk()
            ->assertSee('<a class="reader-back" href="'.route('account.purchases.index').'"', false)
            ->assertSee('id="readerFullscreenTop"', false);
    }

    public function test_document_scrolls_inside_the_reader_only(): void
    {
        $wrap = $this->cssBlock($this->openReader()->getContent(), '.reader-canvas-wrap {');

        $this->assertStringContainsString('overflow-y: auto', $wrap);
        $this->assertStringContainsString('height: 100%', $wrap);
    }

    public function test_document_is_proportionally_padded_and_centred(): void
    {
        $html = $this->openReader()->getContent();

        $wrap = $this->cssBlock($html, '.reader-canvas-wrap {');
        $this->assertStringContainsString('width: 95%', $wrap);
        $this->assertStringContainsString('margin: 0 auto', $wrap);

        $canvas = $this->cssBlock($html, '.reader-canvas {');
        $this->assertStringContainsString('margin: 0 auto', $canvas);
        $this->assertStringContainsString('box-shadow', $canvas);
    }

    public function test_zoomed_pages_can_grow_past_the_container_width(): void
    {
        $html = $this->openReader()->getContent();

        // Clamping the canvas to 100% would squash the aspect ratio on zoom-in.
        $this->assertStringNotContainsString('max-width', $this->cssBlock($html, '.reader-canvas {'));
    }

    public function test_page_fit_is_measured_from_the_scroll_container(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString('scrollEl.clientWidth', $html);
        $this->assertStringNotContainsString('stage.clientWidth', $html);
    }

    public function test_stage_reserves_room_for_the_floating_toolbar(): void
    {
        $stage = $this->cssBlock($this->openReader()->getContent(), '.reader-stage {');

        // Top + bottom padding must clear both fixed pills: the toolbar above
        // and the progress bar below, or they cover the first/last lines.
        $this->assertStringContainsString('padding: 84px 16px 96px', $stage);
    }

    public function test_toolbar_is_a_fixed_translucent_overlay(): void
    {
        $bar = $this->cssBlock($this->openReader()->getContent(), '.reader-bar {');

        $this->assertStringContainsString('position: fixed', $bar);
        $this->assertStringContainsString('bottom: 16px', $bar);
        $this->assertStringContainsString('left: 50%', $bar);
        $this->assertStringContainsString('transform: translateX(-50%)', $bar);
        $this->assertStringContainsString('background: rgba(15, 23, 42, .95)', $bar);
        $this->assertStringContainsString('backdrop-filter: blur(8px)', $bar);
    }

    public function test_toolbar_splits_into_three_sections(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString('grid-template-columns: 1fr auto 1fr', $this->cssBlock($html, '.reader-bar {'));
        $this->assertStringContainsString('reader-bar__group--nav', $html);
        $this->assertStringContainsString('reader-bar__group--center', $html);
        $this->assertStringContainsString('reader-bar__group--right', $html);
    }

    public function test_toolbar_exposes_every_required_control(): void
    {
        $this->openReader()
            ->assertSee('id="readerPrev"', false)
            ->assertSee('id="readerNext"', false)
            ->assertSee('id="readerPageNo"', false)
            ->assertSee('id="readerZoomSlider"', false)
            ->assertSee('id="readerZoomIn"', false)
            ->assertSee('id="readerZoomOut"', false)
            ->assertSee('id="readerZoomReset"', false)
            ->assertSee('id="readerFullscreen"', false);
    }

    public function test_page_indicator_reads_page_x_of_y(): void
    {
        $this->openReader()
            ->assertSee('Page <span id="readerCurrent">1</span> of <span id="readerTotal">', false);
    }

    public function test_zoom_slider_is_a_usable_range_input(): void
    {
        $this->openReader()
            ->assertSee('type="range"', false)
            ->assertSee('id="readerZoomSlider" min="0.5" max="3" step="0.1" value="1"', false);
    }

    public function test_zoom_slider_updates_the_label_live_and_rerenders_on_release(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString("zoomSlider.addEventListener('input'", $html);
        $this->assertStringContainsString("zoomSlider.addEventListener('change'", $html);
        // The drag itself must not re-render, otherwise the page jitters.
        $this->assertStringContainsString('zoomResetBtn.textContent = Math.round(state.zoom * 100) + \'%\';', $html);
    }

    public function test_zoom_slider_stays_in_sync_with_the_zoom_buttons(): void
    {
        $this->openReader()
            ->assertSee('zoomSlider.value = String(state.zoom);', false);
    }

    public function test_zoom_is_clamped_to_a_sane_range(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString('Math.min(3, Math.max(0.5,', $html);
    }

    public function test_fullscreen_toggle_uses_the_native_api_with_a_fallback(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString('root.requestFullscreen', $html);
        $this->assertStringContainsString("root.classList.add('reader--immersive')", $html);
        $this->assertStringContainsString("document.addEventListener('fullscreenchange'", $html);
    }

    public function test_fullscreen_button_reports_its_state(): void
    {
        $this->openReader()
            ->assertSee('id="readerFsLabel"', false)
            ->assertSee("btn.setAttribute('aria-pressed'", false);
    }

    public function test_fullscreen_refits_the_page_for_the_new_viewport(): void
    {
        $this->openReader()
            ->assertSee('if (state.pdf && !state.rendering) renderPage(state.current);', false);
    }

    public function test_escape_leaves_the_fullscreen_fallback(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString("e.key === 'Escape' && !isFullscreen()", $html);
        $this->assertStringContainsString("root.classList.remove('reader--immersive')", $html);
    }

    public function test_mobile_drops_padding_and_goes_full_width(): void
    {
        $mobile = $this->mobileBlock($this->openReader()->getContent());

        $this->assertStringContainsString('.reader-stage { padding: 0; }', $mobile);
        $this->assertStringContainsString('.reader-canvas-wrap { width: 100%', $mobile);
        $this->assertStringContainsString('padding: 0;', $mobile);
    }

    public function test_mobile_condenses_toolbar_labels_to_icons(): void
    {
        $mobile = $this->mobileBlock($this->openReader()->getContent());

        $this->assertStringContainsString('.reader-bar .reader-lbl { display: none; }', $mobile);
        $this->assertStringContainsString('.reader-zoom-slider { display: none; }', $mobile);
        $this->assertStringContainsString('grid-template-columns: auto 1fr auto', $mobile);

        // The chevron glyphs remain as the icon-only affordance.
        $this->openReader()
            ->assertSee('class="reader-ico" aria-hidden="true">&lsaquo;</span>', false)
            ->assertSee('class="reader-ico" aria-hidden="true">&rsaquo;</span>', false);
    }

    public function test_mobile_shrinks_top_bar_typography(): void
    {
        $mobile = $this->mobileBlock($this->openReader()->getContent());

        $this->assertStringContainsString('.reader-title { font-size: .88rem; }', $mobile);
        $this->assertStringContainsString('.reader-back .reader-lbl { display: none; }', $mobile);
        $this->assertStringContainsString('height: 50px', $mobile);
    }

    public function test_keyboard_paging_and_zoom_shortcuts_are_preserved(): void
    {
        $html = $this->openReader()->getContent();

        $this->assertStringContainsString("e.key === 'ArrowLeft'", $html);
        $this->assertStringContainsString("e.key === 'ArrowRight'", $html);
        $this->assertStringContainsString("e.key === '+'", $html);
        $this->assertStringContainsString("e.key === 'Escape'", $html);
    }
}

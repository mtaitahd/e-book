<?php

namespace Tests\Unit;

use App\Services\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * The native reader renders chapter HTML unescaped, so this allowlist is the
 * only thing standing between an editor and a stored-XSS. These tests are the
 * regression net for that boundary.
 */
class HtmlSanitizerTest extends TestCase
{
    private HtmlSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new HtmlSanitizer;
    }

    public function test_it_keeps_ordinary_editorial_markup(): void
    {
        $html = '<h2>Chapter One</h2>'
            .'<p>Some <strong>bold</strong> and <em>italic</em> text.</p>'
            .'<ul><li>First</li><li>Second</li></ul>'
            .'<blockquote><p>Quoted.</p></blockquote>';

        $clean = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<h2>Chapter One</h2>', $clean);
        $this->assertStringContainsString('<strong>bold</strong>', $clean);
        $this->assertStringContainsString('<em>italic</em>', $clean);
        $this->assertStringContainsString('<li>First</li>', $clean);
        $this->assertStringContainsString('<blockquote>', $clean);
    }

    public function test_it_strips_script_tags_and_their_contents(): void
    {
        $clean = $this->sanitizer->sanitize('<p>Before</p><script>alert("xss")</script><p>After</p>');

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('<p>Before</p>', $clean);
        $this->assertStringContainsString('<p>After</p>', $clean);
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $clean = $this->sanitizer->sanitize('<p onclick="steal()" onmouseover="x()">Text</p>');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onmouseover', $clean);
        $this->assertStringNotContainsString('steal', $clean);
        $this->assertStringContainsString('Text', $clean);
    }

    public function test_it_strips_image_error_handlers(): void
    {
        $clean = $this->sanitizer->sanitize('<img src="/x.png" onerror="alert(1)" alt="pic">');

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringContainsString('/x.png', $clean);
    }

    public function test_it_rejects_javascript_urls(): void
    {
        $clean = $this->sanitizer->sanitize('<a href="javascript:alert(1)">click</a>');

        $this->assertStringNotContainsString('javascript', $clean);
        $this->assertStringContainsString('click', $clean);
    }

    public function test_it_rejects_obfuscated_javascript_urls(): void
    {
        // Browsers ignore embedded control characters inside a URL scheme, so
        // the sanitizer has to strip them before deciding what the scheme is.
        $vectors = [
            "<a href=\"java\tscript:alert(1)\">x</a>",
            "<a href=\"java\nscript:alert(1)\">x</a>",
            '<a href="  JaVaScRiPt:alert(1)">x</a>',
            '<a href="&#106;avascript:alert(1)">x</a>',
        ];

        foreach ($vectors as $vector) {
            $clean = $this->sanitizer->sanitize($vector);

            $this->assertStringNotContainsString('script:', $clean, "Failed for: $vector");
            $this->assertStringNotContainsString('alert', $clean, "Failed for: $vector");
        }
    }

    public function test_it_rejects_data_and_vbscript_urls(): void
    {
        $this->assertStringNotContainsString(
            'data:',
            $this->sanitizer->sanitize('<img src="data:text/html;base64,PHNjcmlwdD4=">'),
        );

        $this->assertStringNotContainsString(
            'vbscript',
            $this->sanitizer->sanitize('<a href="vbscript:msgbox(1)">x</a>'),
        );
    }

    public function test_it_allows_safe_url_schemes(): void
    {
        $this->assertStringContainsString('https://example.com', $this->sanitizer->sanitize('<a href="https://example.com">x</a>'));
        $this->assertStringContainsString('mailto:', $this->sanitizer->sanitize('<a href="mailto:a@b.com">x</a>'));
        $this->assertStringContainsString('/relative/path', $this->sanitizer->sanitize('<a href="/relative/path">x</a>'));
        $this->assertStringContainsString('#section', $this->sanitizer->sanitize('<a href="#section">x</a>'));
    }

    public function test_it_rejects_protocol_relative_urls(): void
    {
        $this->assertStringNotContainsString(
            'evil.test',
            $this->sanitizer->sanitize('<img src="//evil.test/pixel.png">'),
        );
    }

    public function test_it_strips_iframe_svg_and_object_subtrees(): void
    {
        $this->assertStringNotContainsString('iframe', $this->sanitizer->sanitize('<iframe src="https://evil.test"></iframe>'));
        $this->assertStringNotContainsString('svg', $this->sanitizer->sanitize('<svg><script>alert(1)</script></svg>'));
        $this->assertStringNotContainsString('object', $this->sanitizer->sanitize('<object data="x.swf"></object>'));
        $this->assertStringNotContainsString('form', $this->sanitizer->sanitize('<form action="/x"><input name="a"></form>'));
    }

    public function test_it_removes_style_attributes(): void
    {
        $clean = $this->sanitizer->sanitize('<p style="position:fixed;top:0;left:0;width:100vw;height:100vh">Cover</p>');

        $this->assertStringNotContainsString('style', $clean);
        $this->assertStringNotContainsString('position', $clean);
    }

    public function test_it_removes_class_and_id_attributes(): void
    {
        $clean = $this->sanitizer->sanitize('<div class="reader-chrome" id="overlay">Text</div>');

        $this->assertStringNotContainsString('class', $clean);
        $this->assertStringNotContainsString('id=', $clean);
        $this->assertStringContainsString('Text', $clean);
    }

    public function test_it_unwraps_unknown_tags_but_keeps_their_text(): void
    {
        $clean = $this->sanitizer->sanitize('<marquee>Keep this prose</marquee>');

        // marquee is dropped as a subtree, so no text leaks out of it.
        $this->assertStringNotContainsString('marquee', $clean);

        $custom = $this->sanitizer->sanitize('<my-widget data-x="1">Keep this prose</my-widget>');
        $this->assertStringNotContainsString('my-widget', $custom);
        $this->assertStringNotContainsString('data-x', $custom);
        $this->assertStringContainsString('Keep this prose', $custom);
    }

    public function test_it_strips_html_comments(): void
    {
        $clean = $this->sanitizer->sanitize('<p>a</p><!-- [if IE]><script>alert(1)</script><![endif] --><p>b</p>');

        $this->assertStringNotContainsString('<!--', $clean);
        $this->assertStringNotContainsString('alert', $clean);
    }

    public function test_it_removes_link_meta_and_base_tags(): void
    {
        $this->assertStringNotContainsString('stylesheet', $this->sanitizer->sanitize('<link rel="stylesheet" href="//evil.test/x.css">'));
        $this->assertStringNotContainsString('http-equiv', $this->sanitizer->sanitize('<meta http-equiv="refresh" content="0;url=//evil.test">'));
    }

    public function test_it_preserves_footnote_and_formula_markup(): void
    {
        $clean = $this->sanitizer->sanitize('<p>H<sub>2</sub>O and E=mc<sup>2</sup></p>');

        $this->assertStringContainsString('<sub>2</sub>', $clean);
        $this->assertStringContainsString('<sup>2</sup>', $clean);
    }

    public function test_it_preserves_table_structure_attributes(): void
    {
        $clean = $this->sanitizer->sanitize(
            '<table><thead><tr><th scope="col">Head</th></tr></thead><tbody><tr><td colspan="2">Cell</td></tr></tbody></table>'
        );

        $this->assertStringContainsString('colspan="2"', $clean);
        $this->assertStringContainsString('scope="col"', $clean);
    }

    public function test_it_handles_empty_and_null_input(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(null));
        $this->assertSame('', $this->sanitizer->sanitize(''));
        $this->assertSame('', $this->sanitizer->sanitize('   '));
    }

    public function test_it_preserves_utf8_text(): void
    {
        $clean = $this->sanitizer->sanitize('<p>Habari za asubuhi — mchana mzuri 🌞</p>');

        $this->assertStringContainsString('Habari za asubuhi', $clean);
        $this->assertStringContainsString('🌞', $clean);
    }

    public function test_it_handles_malformed_markup_without_throwing(): void
    {
        $vectors = [
            '<p>unclosed',
            '</p></div></span>',
            '<p><strong>bad nesting</em></strong></p>',
            '<<<>>>',
            '<p '.$this->longGarbage(),
        ];

        foreach ($vectors as $vector) {
            $clean = $this->sanitizer->sanitize($vector);

            $this->assertIsString($clean);
        }
    }

    public function test_to_plain_text_strips_all_markup(): void
    {
        $text = $this->sanitizer->toPlainText('<h2>Title</h2><p>Body <strong>text</strong>.</p>');

        $this->assertSame('TitleBody text.', $text);
    }

    public function test_to_plain_text_respects_the_limit(): void
    {
        $text = $this->sanitizer->toPlainText('<p>'.str_repeat('word ', 50).'</p>', 20);

        $this->assertLessThanOrEqual(20, mb_strlen($text));
    }

    public function test_to_plain_text_does_not_leak_script_bodies(): void
    {
        $text = $this->sanitizer->toPlainText('<p>Safe</p><script>alert(1)</script>');

        $this->assertSame('Safe', $text);
    }

    private function longGarbage(): string
    {
        return str_repeat('a="b" ', 200);
    }
}

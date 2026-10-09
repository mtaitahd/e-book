<?php

namespace App\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allowlist HTML sanitizer for native-reader chapter content.
 *
 * The reader renders stored chapter HTML unescaped, so this class is the
 * security boundary for that content. It follows a strict allowlist: a tag,
 * an attribute or a URL scheme that is not explicitly permitted is removed.
 * Nothing is ever "cleaned up" by trying to detect an attack — an unknown
 * construct is simply not in the list, so it cannot survive.
 *
 * Deliberately not allowed:
 *  - `style` / `class` / `id`, because arbitrary CSS enables UI-redress
 *    overlays that could sit on top of the reader's own chrome.
 *  - `target` / `rel`, so an injected link can never gain access to the
 *    opener window.
 *  - `iframe`, `svg`, `object`, `form` and friends, whose subtrees are
 *    discarded entirely rather than unwrapped.
 */
class HtmlSanitizer
{
    /**
     * Structural, text and typographic tags an editor is allowed to use.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'div', 'span',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins', 'mark', 'small',
        'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        'blockquote', 'pre', 'code', 'kbd', 'samp', 'var', 'q', 'cite', 'abbr',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
        'colgroup', 'col',
    ];

    /**
     * Tags removed together with everything inside them. Unwrapping a
     * `<script>` would leak its body into the page as visible text, and
     * unwrapping an `<svg>` would leak executable markup, so these vanish
     * completely.
     *
     * @var list<string>
     */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
        'applet', 'form', 'input', 'button', 'select', 'option', 'optgroup',
        'textarea', 'label', 'fieldset', 'legend', 'datalist', 'output',
        'link', 'meta', 'base', 'title', 'head', 'html', 'body',
        'svg', 'math', 'noscript', 'template', 'slot',
        'audio', 'video', 'source', 'track', 'canvas', 'map', 'area',
        'portal', 'dialog', 'marquee', 'xmp', 'plaintext', 'listing',
        'noembed', 'noframes', 'bgsound', 'xml', 'import', 'keygen',
    ];

    /**
     * Attributes permitted per tag. A tag that is not listed here keeps no
     * attributes at all.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'col' => ['span'],
        'colgroup' => ['span'],
        'ol' => ['start', 'reversed', 'type'],
    ];

    /**
     * Attributes carrying a URL, which must pass the scheme check.
     *
     * @var list<string>
     */
    private const URL_ATTRIBUTES = ['href', 'src'];

    /**
     * The only URL schemes an author may link to. Everything else — most
     * importantly `javascript:`, `data:` and `vbscript:` — is dropped.
     *
     * @var list<string>
     */
    private const ALLOWED_URL_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Sanitize a fragment of untrusted HTML down to the allowlist.
     */
    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $document = $this->parse($html);

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $this->cleanChildren($body);

        return $this->innerHtmlOf($body);
    }

    /**
     * Strip all markup and return plain text, e.g. for meta descriptions.
     */
    public function toPlainText(?string $html, int $limit = 0): string
    {
        $text = $this->sanitize($html);

        if ($text === '') {
            return '';
        }

        $document = $this->parse($text);
        $body = $document->getElementsByTagName('body')->item(0);

        $plain = $body === null ? '' : trim($body->textContent);
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        if ($limit > 0 && mb_strlen($plain) > $limit) {
            $plain = rtrim(mb_substr($plain, 0, $limit));
        }

        return $plain;
    }

    /**
     * Parse a fragment into a UTF-8 document.
     *
     * LIBXML_NONET stops the parser from touching the network while reading
     * the fragment, and the XML encoding declaration pins UTF-8 so
     * multi-byte characters are not mangled by the latin1 default.
     */
    private function parse(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document->loadHTML(
            '<?xml encoding="UTF-8" ?>'.$html,
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    /**
     * Serialize only the children of a node, never the wrapper itself.
     */
    private function innerHtmlOf(DOMNode $node): string
    {
        $html = '';

        foreach (iterator_to_array($node->childNodes, false) as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return trim($html);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        // Snapshot first: the walk removes and moves nodes, which would
        // otherwise invalidate a live child list mid-iteration.
        foreach (iterator_to_array($parent->childNodes, false) as $child) {
            $this->cleanNode($child);
        }
    }

    private function cleanNode(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $node->parentNode?->removeChild($node);

                return;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // An unknown wrapper is harmless once its own attributes are
                // gone, so keep the prose it contains instead of dropping the
                // editor's text, then replace the element with its contents.
                $this->cleanChildren($node);
                $this->unwrap($node);

                return;
            }

            $this->cleanAttributes($node, $tag);
            $this->cleanChildren($node);

            return;
        }

        // Text is the whole point. Comments, processing instructions and
        // CDATA are dropped: they carry no editorial value and comments have
        // historically been used to smuggle conditional payloads.
        if (! $node instanceof DOMText) {
            $node->parentNode?->removeChild($node);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        /** @var list<DOMAttr> $attributes */
        $attributes = $element->hasAttributes()
            ? iterator_to_array($element->attributes, false)
            : [];

        foreach ($attributes as $attribute) {
            $name = strtolower($attribute->name);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);

                continue;
            }

            if (! in_array($name, self::URL_ATTRIBUTES, true)) {
                continue;
            }

            $url = $this->sanitizeUrl($attribute->value);

            if ($url === null) {
                $element->removeAttribute($attribute->name);
            } else {
                $element->setAttribute($attribute->name, $url);
            }
        }
    }

    /**
     * Return a safe URL, or null when the value must be dropped.
     *
     * Whitespace and control characters are removed before the scheme is read
     * because browsers ignore them when resolving a URL — without this,
     * "java\tscript:alert(1)" would look like a harmless relative path.
     */
    private function sanitizeUrl(string $value): ?string
    {
        $clean = preg_replace('/[\x00-\x20\x7F]+/', '', $value) ?? '';
        $clean = trim($clean);

        if ($clean === '') {
            return null;
        }

        // "//host/path" is off-site, so treat it as absolute rather than
        // letting it pass as a harmless relative reference.
        if (str_starts_with($clean, '//')) {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $clean, $matches) === 1) {
            if (! in_array(strtolower($matches[1]), self::ALLOWED_URL_SCHEMES, true)) {
                return null;
            }
        }

        return $clean;
    }

    /**
     * Replace an element with its own children.
     */
    private function unwrap(DOMNode $node): void
    {
        $parent = $node->parentNode;

        if ($parent === null) {
            return;
        }

        while ($node->firstChild !== null) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }
}

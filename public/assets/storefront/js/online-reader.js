/**
 * Native online reader.
 *
 * Pages are produced by measuring real layout rather than guessing from word
 * counts: nodes are moved into a page and the page's own scrollHeight decides
 * when it is full. A block that overflows is split (recursively, at word
 * boundaries for text) so there are no half-rendered pages and no dead space
 * bigger than one line. Page geometry is recomputed whenever the font size,
 * window size or theme changes, and the reader restores the position by page
 * number so nothing is lost while typing.
 *
 * Chapter HTML arrives already reduced to the sanitizer's allowlist (it is
 * sanitized again on read, server side), which is the same trust level as the
 * admin preview.
 */
(function () {
    'use strict';

    var root = document.getElementById('online-reader');
    if (!root) {
        return;
    }

    var configNode = document.getElementById('online-reader-config');
    if (!configNode) {
        return;
    }

    var config = JSON.parse(configNode.textContent);
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var TOLERANCE = 2; // sub-pixel rounding slack when comparing heights
    var TURN_MS = 640; // one physical page turn, matched by the CSS flip animation
    var flipSeq = 0;   // invalidates a pending flip finish after a cancel

    var spreadQuery = window.matchMedia ? window.matchMedia('(min-width: 901px)') : null;
    var reduceMotion = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : { matches: false };

    var state = {
        book: config.book,
        chapters: config.chapters || [],
        bookmarks: config.bookmarks || [],
        urls: config.urls,
        csrf: csrfMeta ? csrfMeta.getAttribute('content') : '',
        chapterIndex: 0,
        page: 1,
        totalPages: 1,
        theme: config.theme || 'book',
        fontSize: config.font_size || 1,
        loading: false,
        repaginating: false,
        // True while the front cover is closed over the book: no page shows
        // until the reader flips it open.
        onCover: false,
        // A sheet is mid-turn; navigation must not start a second one.
        flipping: false,
        // Source blocks of the loaded chapter; null until the first chapter
        // arrives. relayout() must never paginate before that.
        blocks: null
    };

    var el = {
        stage: document.getElementById('orp-stage'),
        pages: document.getElementById('orp-pages'),
        status: document.getElementById('orp-status'),
        progressBar: document.getElementById('orp-progress-bar'),
        chapterLabel: document.getElementById('orp-chapter-label'),
        bookTitle: document.getElementById('orp-book-title'),
        pageNow: document.getElementById('orp-page-now'),
        pageTotal: document.getElementById('orp-page-total'),
        percent: document.getElementById('orp-percent'),
        prev: document.getElementById('orp-prev'),
        next: document.getElementById('orp-next'),
        prevChapter: document.getElementById('orp-prev-chapter'),
        nextChapter: document.getElementById('orp-next-chapter'),
        fontIn: document.getElementById('orp-font-in'),
        fontOut: document.getElementById('orp-font-out'),
        fontLabel: document.getElementById('orp-font-label'),
        themeBtn: document.getElementById('orp-theme'),
        fullscreenBtn: document.getElementById('orp-fullscreen'),
        tocBtn: document.getElementById('orp-toc-toggle'),
        tocPanel: document.getElementById('orp-toc'),
        tocList: document.getElementById('orp-toc-list'),
        markBtn: document.getElementById('orp-mark'),
        markAdd: document.getElementById('orp-mark-add'),
        markPanel: document.getElementById('orp-marks'),
        markList: document.getElementById('orp-mark-list'),
        pdfLink: document.getElementById('orp-pdf-link'),
        saveState: document.getElementById('orp-save-state'),
        cover: document.getElementById('orp-cover'),
        toolPrev: document.getElementById('orp-tool-prev'),
        toolNext: document.getElementById('orp-tool-next'),
        progressPin: document.getElementById('orp-progress-pin')
    };

    /* ------------------------------------------------------------------ *
     * Small helpers
     * ------------------------------------------------------------------ */

    function clamp(value, min, max) {
        return Math.min(Math.max(value, min), max);
    }

    /** The paginated pages only — the cover and any turning sheet share the
     *  container but must never shift a page's number. */
    function pageNodes() {
        var kids = el.pages.children;
        var nodes = [];
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].classList && kids[i].classList.contains('orp-page')) {
                nodes.push(kids[i]);
            }
        }
        return nodes;
    }

    /* ------------------------------------------------------------------ *
     * The spread
     *
     * On a wide screen the reader shows two pages at a time, like an open
     * book: the current page goes on the left and the next one on the right.
     * state.page therefore always holds the odd, left-hand page of the
     * spread. On a narrow screen the same code runs single-page, stepping
     * one page at a time.
     * ------------------------------------------------------------------ */

    function isSpread() {
        return !!(spreadQuery && spreadQuery.matches);
    }

    function stepSize() {
        return isSpread() ? 2 : 1;
    }

    /** Snap a page number onto the left-hand page of the current view. */
    function normalizePage(number) {
        var n = clamp(Math.round(number || 1), 1, state.totalPages);

        if (isSpread() && n % 2 === 0 && n > 1) {
            n = n - 1;
        }

        return n;
    }

    /**
     * Rebuild which pages are showing. Every page stays in place (the
     * pagination engine measures them all); visibility is the only thing
     * that changes, so turning is instant. While the cover is closed no
     * page shows at all.
     */
    function paintSpread() {
        var pages = pageNodes();
        var from = state.page;
        var to = isSpread() ? state.page + 1 : state.page;

        for (var i = 0; i < pages.length; i++) {
            var number = i + 1;
            pages[i].classList.toggle('is-visible',
                !state.onCover && number >= from && number <= to);
        }
    }

    /** Show exactly these page numbers (used while a sheet is mid-turn). */
    function paintOnly(numbers) {
        var pages = pageNodes();
        for (var i = 0; i < pages.length; i++) {
            pages[i].classList.toggle('is-visible', numbers.indexOf(i + 1) !== -1);
        }
    }

    /** A detached copy of one page, for riding inside a turning sheet. */
    function pageClone(number) {
        var src = pageNodes()[number - 1];
        if (!src) {
            return document.createElement('div');
        }
        var clone = src.cloneNode(true);
        clone.classList.add('is-visible');
        return clone;
    }

    /* ------------------------------------------------------------------ *
     * The front cover
     *
     * A fresh book opens closed: the cover sits over the right half of the
     * stage until the reader turns it. Its art is built once from the book's
     * metadata and reused as the front face of the turning sheet.
     * ------------------------------------------------------------------ */

    function buildCoverArt() {
        var art = document.createElement('div');
        art.className = 'orp-cover__art';

        if (state.book.cover) {
            art.className += ' has-img';
            var img = document.createElement('img');
            img.src = state.book.cover;
            img.alt = '';
            art.appendChild(img);
            var scrim = document.createElement('div');
            scrim.className = 'orp-cover__scrim';
            art.appendChild(scrim);
        }

        var brand = document.createElement('span');
        brand.className = 'orp-cover__brand';
        brand.textContent = 'E-Book';

        var title = document.createElement('span');
        title.className = 'orp-cover__title';
        title.textContent = state.book.title;

        var author = document.createElement('span');
        author.className = 'orp-cover__author';
        author.textContent = state.book.author || '';

        var frame = document.createElement('div');
        frame.className = 'orp-cover__frame';

        art.appendChild(brand);
        art.appendChild(title);
        art.appendChild(author);
        art.appendChild(frame);
        return art;
    }

    function setOnCover(onCover) {
        state.onCover = onCover;
        if (el.cover) {
            el.cover.hidden = !onCover;
        }
    }

    /* ------------------------------------------------------------------ *
     * The turning sheet
     *
     * One leaf caught mid-turn: an opaque front face (the page leaving the
     * spread) hinged at the spine onto an opaque back face (the page that
     * arrives). The base pages underneath are already switched to the
     * incoming spread, so when the sheet lands and is removed the eye sees
     * no change at all. CSS plays the rotation; a timer always finishes the
     * turn, which keeps the flow working where animations never run.
     * ------------------------------------------------------------------ */

    function appendSheet(frontNode, backNode, single, direction) {
        var sheet = document.createElement('div');
        sheet.className = 'orp-turn'
            + (single ? ' orp-turn--single' : '')
            + (direction === 'prev' ? ' orp-turn--prev' : ' orp-turn--next');

        var front = document.createElement('div');
        front.className = 'orp-turn__face orp-turn__face--front';
        front.appendChild(frontNode);

        var back = document.createElement('div');
        back.className = 'orp-turn__face orp-turn__face--back';
        back.appendChild(backNode);

        sheet.appendChild(front);
        sheet.appendChild(back);
        el.pages.appendChild(sheet);
        return sheet;
    }

    function removeNode(node) {
        if (node && node.parentNode) {
            node.parentNode.removeChild(node);
        }
    }

    /** Drop any in-flight turn (a chapter change must never inherit one). */
    function cancelFlip() {
        flipSeq++;
        var sheets = el.pages.querySelectorAll('.orp-turn');
        for (var i = 0; i < sheets.length; i++) {
            removeNode(sheets[i]);
        }
        state.flipping = false;
        if (turnTimer) {
            window.clearTimeout(turnTimer);
            turnTimer = null;
        }
        el.pages.classList.remove('turned-next', 'turned-prev');
    }

    function finishSheet(sheet, seq, done) {
        window.setTimeout(function () {
            if (seq !== flipSeq) {
                return;
            }
            if (done) {
                done(); // cover flips settle their state before the repaint
            }
            removeNode(sheet);
            state.flipping = false;
            paintSpread();
            syncControls();
            scheduleSave();
        }, TURN_MS + 40);
    }

    var turnTimer = null;

    /** Direction-aware page-turn flourish; skipped when motion is reduced. */
    function playTurn(direction) {
        if (!direction || reduceMotion.matches) {
            return;
        }

        el.pages.classList.remove('turned-next', 'turned-prev');
        void el.pages.offsetWidth; // restart the animation
        el.pages.classList.add(direction === 'prev' ? 'turned-prev' : 'turned-next');

        if (turnTimer) {
            window.clearTimeout(turnTimer);
        }
        turnTimer = window.setTimeout(function () {
            el.pages.classList.remove('turned-next', 'turned-prev');
            turnTimer = null;
        }, TURN_MS + 80);
    }

    function showStatus(message, isError) {
        el.status.hidden = false;
        el.status.textContent = message;
        el.status.classList.toggle('orp-status--error', !!isError);
    }

    function clearStatus() {
        el.status.hidden = true;
        el.status.textContent = '';
    }

    function currentChapter() {
        return state.chapters[state.chapterIndex] || null;
    }

    function request(url, options) {
        return fetch(url, Object.assign({
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }, options || {}));
    }

    /* ------------------------------------------------------------------ *
     * Source normalisation
     *
     * Sanitized chapter HTML is a fragment, so it can contain top level text
     * and inline elements. Everything is grouped into block level children so
     * the pagination loop only ever deals with blocks.
     * ------------------------------------------------------------------ */

    var INLINE_TAGS = { A: 1, ABBR: 1, B: 1, BDI: 1, BDO: 1, BR: 1, CITE: 1, CODE: 1, DATA: 1,
        DFN: 1, EM: 1, I: 1, IMG: 1, KBD: 1, MARK: 1, Q: 1, RUBY: 1, S: 1, SAMP: 1, SMALL: 1,
        SPAN: 1, STRONG: 1, SUB: 1, SUP: 1, TIME: 1, U: 1, VAR: 1, WBR: 1 };

    function isBlock(node) {
        if (node.nodeType !== 1) {
            return false;
        }
        return !INLINE_TAGS[node.tagName];
    }

    function normalise(fragment) {
        var wrapper = document.createElement('div');
        while (fragment.firstChild) {
            wrapper.appendChild(fragment.firstChild);
        }

        var blocks = document.createDocumentFragment();
        var pending = document.createDocumentFragment();

        function flushPending() {
            if (!pending.childNodes.length) {
                return;
            }
            var para = document.createElement('p');
            while (pending.firstChild) {
                para.appendChild(pending.firstChild);
            }
            if (para.textContent.trim() || para.querySelector('img')) {
                blocks.appendChild(para);
            }
            pending.textContent = '';
        }

        while (wrapper.firstChild) {
            var node = wrapper.firstChild;
            if (isBlock(node)) {
                flushPending();
                blocks.appendChild(node);
                continue;
            }
            if (node.nodeType === 3 && !node.nodeValue.trim()) {
                wrapper.removeChild(node);
                continue;
            }
            pending.appendChild(node);
        }
        flushPending();

        return blocks;
    }

    /* ------------------------------------------------------------------ *
     * Pagination
     * ------------------------------------------------------------------ */

    /** Remove only the pages and any turning sheet — the front cover stays. */
    function clearPages() {
        var kids = Array.prototype.slice.call(el.pages.children);
        for (var i = 0; i < kids.length; i++) {
            if (kids[i].classList &&
                (kids[i].classList.contains('orp-page') ||
                 kids[i].classList.contains('orp-turn'))) {
                el.pages.removeChild(kids[i]);
            }
        }
    }

    function makePage() {
        var page = document.createElement('div');
        // Side classes fix the spread geometry in CSS; nth-child would break
        // because the cover and turning sheets share the container.
        page.className = 'orp-page ' + (pageNodes().length % 2 === 0
            ? 'orp-page--left'
            : 'orp-page--right');
        var inner = document.createElement('div');
        inner.className = 'orp-page__inner orp-content';
        page.appendChild(inner);
        el.pages.appendChild(page);

        // Printer-style folio in the bottom margin of every leaf.
        var num = document.createElement('span');
        num.className = 'orp-page__num';
        num.textContent = String(pageNodes().length);
        page.appendChild(num);

        return { node: page, inner: inner };
    }

    function full(inner) {
        return inner.scrollHeight > inner.clientHeight + TOLERANCE;
    }

    /**
     * Split a text node so the text that fits stays put. Returns the overflow
     * string, or null when the node cannot be split any further.
     */
    function splitText(textNode, inner) {
        var text = textNode.nodeValue;
        var low = 1;
        var high = text.length;
        var fit = 0;

        while (low <= high) {
            var mid = (low + high) >> 1;
            textNode.nodeValue = text.slice(0, mid);
            if (full(inner)) {
                high = mid - 1;
            } else {
                fit = mid;
                low = mid + 1;
            }
        }

        if (fit === 0) {
            textNode.nodeValue = text;
            return null;
        }

        // Prefer a word boundary so a page never starts mid-word.
        var cut = fit;
        if (fit < text.length) {
            var breakAt = text.lastIndexOf(' ', fit);
            if (breakAt > 0) {
                cut = breakAt + 1;
            }
        }

        var head = text.slice(0, cut);
        var tail = text.slice(cut);
        textNode.nodeValue = head;

        return tail || null;
    }

    /**
     * `block` is the last child of `inner` and currently overflows it. Split it
     * in place, returning { head, tail } where tail is detached, or null when
     * the block has to move to the next page as a whole.
     */
    function splitBlock(block, inner) {
        if (block.nodeType === 3) {
            var text = splitText(block, inner);
            if (text === null) {
                return null;
            }
            var textWrapper = document.createElement('p');
            textWrapper.appendChild(document.createTextNode(text));
            return { head: block, tail: textWrapper };
        }

        if (block.nodeType !== 1) {
            return null;
        }

        if (block.children.length === 0) {
            // Leaf element (p, li, h2, blockquote, td, ...): split its text.
            var leaf = block.firstChild && block.firstChild.nodeType === 3 ? block.firstChild : null;
            if (leaf === null) {
                return null; // empty or atomic (img, hr, table, ...)
            }
            var overflow = splitText(leaf, inner);
            if (overflow === null) {
                return null;
            }
            var clone = block.cloneNode(false);
            clone.appendChild(document.createTextNode(overflow));
            return { head: block, tail: clone };
        }

        // Container (ul, table, blockquote > p, ...): recurse into the tail
        // end, which is where the overflow always is.
        var last = block.lastElementChild;
        var result = last ? splitBlock(last, inner) : null;
        if (result === null) {
            return null;
        }

        var tailWrap = block.cloneNode(false);
        tailWrap.appendChild(result.tail);
        return { head: block, tail: tailWrap };
    }

    function paginate(blocks) {
        clearPages();

        var page = makePage();
        var pages = [page];

        var node;
        while ((node = blocks.firstChild)) {
            page.inner.appendChild(node);

            if (full(page.inner)) {
                var split = splitBlock(node, page.inner);

                if (split !== null) {
                    node = split.head;
                    var next = makePage();
                    next.inner.appendChild(split.tail);
                    pages.push(next);
                    page = next;
                    continue;
                }

                // Not splittable: give it a page of its own.
                page.inner.removeChild(node);
                if (page.inner.childNodes.length === 0) {
                    page.inner.appendChild(node);
                } else {
                    var solo = makePage();
                    solo.inner.appendChild(node);
                    pages.push(solo);
                    page = solo;
                }
            }
        }

        return pages;
    }

    function pageHeight() {
        var styles = window.getComputedStyle(el.stage);
        var usable = el.stage.clientHeight
            - parseFloat(styles.paddingTop || 0)
            - parseFloat(styles.paddingBottom || 0);

        return Math.max(usable, 240);
    }

    /**
     * Repaginate for the current geometry, then put the reader back on the
     * page it was on.
     */
    function relayout() {
        // Nothing to paginate until the first chapter has been fetched.
        // Returning early keeps boot (font/theme restore) from throwing and
        // killing the whole script before loadChapter() ever runs.
        if (state.repaginating || !state.blocks) {
            return;
        }
        state.repaginating = true;

        try {
            var target = normalizePage(clamp(state.page, 1, state.totalPages));
            var height = pageHeight();

            el.pages.style.setProperty('--orp-page-height', height + 'px');

            // paginate() moves nodes out of its argument, so hand it a copy:
            // the source blocks have to survive every reflow (font size,
            // window resize, theme change) instead of being drained once.
            var pages = paginate(state.blocks.cloneNode(true));
            state.totalPages = pages.length;
            state.page = normalizePage(clamp(target, 1, state.totalPages));

            el.pageTotal.textContent = state.totalPages;
            goToPage(state.page, false);
            updateProgressBar();
        } finally {
            state.repaginating = false;
        }
    }

    /* ------------------------------------------------------------------ *
     * Navigation
     *
     * Nothing scrolls: the stage holds exactly one spread at a time. The
     * page buttons play a real 3D leaf turn; explicit jumps (TOC, bookmarks,
     * Home/End, restored progress) are instant.
     * ------------------------------------------------------------------ */

    function goToPage(number, smooth, direction) {
        if (!pageNodes().length) {
            return;
        }

        var target = normalizePage(clamp(number, 1, state.totalPages));

        // Any jump into content lifts the cover; jumping to the very start
        // of chapter one keeps (or returns to) the closed book.
        if (state.onCover && !(state.chapterIndex === 0 && target <= 1)) {
            setOnCover(false);
        }

        state.page = target;
        paintSpread();
        syncControls();
        scheduleSave();
    }

    /**
     * Turn exactly one step forward or back. One sheet — the page leaving
     * the spread on its front, the page arriving on its back — rotates
     * around the spine while the base pages already hold the incoming
     * spread, so the landing needs no second swap.
     */
    function turnStep(target, direction) {
        if (state.flipping || state.loading || !pageNodes().length) {
            return;
        }
        if (normalizePage(target) === state.page) {
            return;
        }

        var oldPage = state.page;
        state.page = normalizePage(target);
        syncControls();
        playTurn(direction);

        if (reduceMotion.matches) {
            paintSpread();
            scheduleSave();
            return;
        }

        state.flipping = true;
        var seq = ++flipSeq;
        var single = !isSpread();
        var front;
        var back;
        var base;

        if (single) {
            if (direction === 'next') {
                front = pageClone(oldPage);
                back = pageClone(state.page);
                base = [state.page];
            } else {
                front = pageClone(state.page);
                back = pageClone(oldPage);
                base = [oldPage];
            }
        } else if (direction === 'next') {
            front = pageClone(oldPage + 1);
            back = pageClone(state.page);
            base = [oldPage, state.page + 1];
        } else {
            front = pageClone(state.page + 1);
            back = pageClone(oldPage);
            base = [state.page, oldPage + 1];
        }

        paintOnly(base);
        var sheet = appendSheet(front, back, single, direction);
        finishSheet(sheet, seq);
    }

    /** Open the closed cover forward, or close it back over the book. */
    function turnCover(direction) {
        if (state.flipping || state.loading || !pageNodes().length) {
            return;
        }

        playTurn(direction);

        if (reduceMotion.matches) {
            setOnCover(direction === 'prev');
            paintSpread();
            syncControls();
            scheduleSave();
            return;
        }

        state.flipping = true;
        var seq = ++flipSeq;
        var single = !isSpread();

        paintOnly(single ? [1] : [2]);
        var sheet = appendSheet(buildCoverArt(), pageClone(1), single, direction);
        finishSheet(sheet, seq, function () {
            setOnCover(direction === 'prev');
        });
    }

    function prevPage() {
        if (state.flipping || state.loading || state.onCover) {
            return;
        }

        var target = normalizePage(state.page - stepSize());

        if (target >= 1 && target < state.page) {
            turnStep(target, 'prev');
        } else if (state.chapterIndex === 0 && state.page <= 1) {
            turnCover('prev');
        } else {
            previousChapter();
        }
    }

    function nextPage() {
        if (state.flipping || state.loading) {
            return;
        }
        if (state.onCover) {
            turnCover('next');
            return;
        }

        var target = normalizePage(state.page + stepSize());

        if (target > state.page) {
            turnStep(target, 'next');
        } else {
            nextChapter();
        }
    }

    function nextChapter() {
        if (state.chapterIndex < state.chapters.length - 1) {
            loadChapter(state.chapterIndex + 1);
        }
    }

    function previousChapter() {
        if (state.chapterIndex > 0) {
            var previous = state.chapterIndex - 1;
            loadChapter(previous, 0, true);
        }
    }

    function syncControls() {
        var shown = isSpread() && state.page + 1 <= state.totalPages
            ? state.page + '–' + (state.page + 1)
            : String(state.page);

        el.pageNow.textContent = shown;
        el.pageTotal.textContent = state.totalPages;
        el.percent.textContent = Math.round(readingPercent()) + '%';

        var chapter = currentChapter();
        el.chapterLabel.textContent = chapter
            ? 'Chapter ' + (chapter.position || state.chapterIndex + 1) + ' · ' + chapter.title
            : '';

        // At the cover there is nothing before it, but the cover itself must
        // always be able to open — even when the whole book is one page.
        el.prev.disabled = state.onCover ||
            (state.chapterIndex === 0 && state.page <= 1);
        el.next.disabled = !state.onCover &&
            state.chapterIndex >= state.chapters.length - 1 &&
            state.page >= state.totalPages;
        el.prevChapter.disabled = state.chapterIndex === 0;
        el.nextChapter.disabled = state.chapterIndex >= state.chapters.length - 1;

        // The small toolbar chevrons mirror the large side arrows.
        if (el.toolPrev) {
            el.toolPrev.disabled = el.prev.disabled;
        }
        if (el.toolNext) {
            el.toolNext.disabled = el.next.disabled;
        }

        updateProgressBar();
    }

    function updateProgressBar() {
        var pct = clamp(readingPercent(), 0, 100);
        el.progressBar.style.width = pct + '%';
        if (el.progressPin) {
            // Keep the pin inside the track so it never hangs off the ends.
            el.progressPin.style.left = clamp(pct, 2, 98) + '%';
        }
    }

    /**
     * Percentage across the whole book, approximated from the chapter the
     * reader is in plus their position inside it.
     */
    function readingPercent() {
        if (state.chapters.length === 0) {
            return 0;
        }
        var within = state.totalPages > 0 ? (state.page - 1) / state.totalPages : 0;
        return ((state.chapterIndex + within) / state.chapters.length) * 100;
    }

    /* ------------------------------------------------------------------ *
     * Chapter loading
     * ------------------------------------------------------------------ */

    /**
     * @param {boolean} [keepCover] land on the closed front cover instead of
     *   the first page — used when booting at the very start of the book.
     */
    function loadChapter(index, jumpToPage, toEnd, keepCover) {
        if (index < 0 || index >= state.chapters.length) {
            return;
        }

        var chapter = state.chapters[index];
        state.loading = true;
        el.stage.setAttribute('aria-busy', 'true');
        showStatus('Loading “' + chapter.title + '”…');

        var url = state.urls.chapter.replace('__CHAPTER__', chapter.id);

        request(url).then(function (response) {
            if (!response.ok) {
                throw new Error('Chapter could not be loaded.');
            }
            return response.json();
        }).then(function (payload) {
            cancelFlip(); // a fresh chapter never inherits an old turn
            state.chapterIndex = index;
            state.blocks = normalise(document.createRange().createContextualFragment(payload.html));
            setOnCover(!!keepCover);

            el.bookTitle.textContent = state.book.title;
            if (el.pdfLink) {
                el.pdfLink.hidden = !state.book.has_pdf;
            }

            clearStatus();
            relayout();

            if (toEnd) {
                goToPage(state.totalPages, false);
            } else if (jumpToPage) {
                goToPage(jumpToPage, false);
            } else {
                goToPage(1, false);
            }

            renderToc();
            state.loading = false;
            el.stage.setAttribute('aria-busy', 'false');

            // A soft, premium settle as the new chapter spreads onto the
            // stage. Deliberately not a page transition — turns stay 3D.
            el.stage.classList.add('orp-stage--landing');
            window.setTimeout(function () {
                el.stage.classList.remove('orp-stage--landing');
            }, 460);

            scheduleSave(true);
        }).catch(function (error) {
            state.loading = false;
            el.stage.setAttribute('aria-busy', 'false');
            showStatus(error.message || 'This chapter could not be loaded.', true);
        });
    }

    /* ------------------------------------------------------------------ *
     * Progress persistence
     * ------------------------------------------------------------------ */

    var saveTimer = null;

    function progressPayload() {
        var chapter = currentChapter();
        return {
            chapter_id: chapter ? chapter.id : null,
            page: state.page,
            percent: Math.round(readingPercent() * 100) / 100
        };
    }

    function scheduleSave(immediate) {
        if (saveTimer) {
            window.clearTimeout(saveTimer);
        }
        if (immediate) {
            sendProgress();
            return;
        }
        saveTimer = window.setTimeout(sendProgress, 2000);
    }

    function sendProgress() {
        saveTimer = null;
        var chapter = currentChapter();
        if (!chapter) {
            return;
        }

        var body = new URLSearchParams();
        body.set('chapter_id', chapter.id);
        body.set('page', state.page);
        body.set('percent', readingPercent().toFixed(2));

        request(state.urls.progress, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-TOKEN': state.csrf
            },
            body: body.toString()
        }).then(function (response) {
            if (response.ok) {
                el.saveState.textContent = 'Saved';
            }
        }).catch(function () {
            el.saveState.textContent = 'Not saved';
        });
    }

    /* ------------------------------------------------------------------ *
     * Table of contents + bookmarks
     * ------------------------------------------------------------------ */

    function renderToc() {
        el.tocList.textContent = '';

        state.chapters.forEach(function (chapter, index) {
            var item = document.createElement('li');
            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = chapter.title;

            var meta = document.createElement('span');
            meta.className = 'orp-list__meta';
            meta.textContent = 'Chapter ' + (chapter.position || index + 1)
                + (chapter.is_free ? ' · free preview' : '');
            button.appendChild(meta);

            if (index === state.chapterIndex) {
                button.setAttribute('aria-current', 'true');
            }
            button.addEventListener('click', function () {
                loadChapter(index);
                togglePanel('toc', false);
            });

            item.appendChild(button);
            el.tocList.appendChild(item);
        });
    }

    function renderBookmarks() {
        el.markList.textContent = '';

        if (state.bookmarks.length === 0) {
            var empty = document.createElement('li');
            empty.className = 'orp-empty';
            empty.textContent = 'No bookmarks yet.';
            el.markList.appendChild(empty);
            return;
        }

        var titles = {};
        state.chapters.forEach(function (chapter) {
            titles[chapter.id] = chapter;
        });

        state.bookmarks.forEach(function (bookmark) {
            var item = document.createElement('li');
            var chapter = titles[bookmark.chapter_id];

            var button = document.createElement('button');
            button.type = 'button';
            button.textContent = bookmark.label || ('Page ' + bookmark.page);
            var meta = document.createElement('span');
            meta.className = 'orp-list__meta';
            meta.textContent = (chapter ? chapter.title + ' · ' : '') + 'Page ' + bookmark.page;
            button.appendChild(meta);

            button.addEventListener('click', function () {
                if (chapter && chapter.id !== (currentChapter() || {}).id) {
                    loadChapter(state.chapters.indexOf(chapter), bookmark.page);
                } else {
                    goToPage(bookmark.page, true,
                        bookmark.page > state.page ? 'next' : (bookmark.page < state.page ? 'prev' : null));
                }
                togglePanel('marks', false);
            });

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'orp-list__del';
            remove.setAttribute('aria-label', 'Remove bookmark on page ' + bookmark.page);
            remove.textContent = '×';
            remove.addEventListener('click', function (event) {
                event.stopPropagation();
                destroyBookmark(bookmark);
            });

            item.appendChild(button);
            item.appendChild(remove);
            el.markList.appendChild(item);
        });
    }

    function addBookmark() {
        var chapter = currentChapter();
        if (!chapter) {
            return;
        }

        var body = new URLSearchParams();
        body.set('chapter_id', chapter.id);
        body.set('page', state.page);

        request(state.urls.bookmark, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-CSRF-TOKEN': state.csrf
            },
            body: body.toString()
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Bookmark could not be saved.');
            }
            return response.json();
        }).then(function (payload) {
            state.bookmarks = payload.bookmarks || state.bookmarks;
            renderBookmarks();
            togglePanel('marks', true);
        }).catch(function (error) {
            showStatus(error.message, true);
        });
    }

    function destroyBookmark(bookmark) {
        request(state.urls.bookmark + '/' + bookmark.id, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': state.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('Bookmark could not be removed.');
            }
            return response.json();
        }).then(function (payload) {
            state.bookmarks = payload.bookmarks || [];
            renderBookmarks();
        }).catch(function (error) {
            showStatus(error.message, true);
        });
    }

    /* ------------------------------------------------------------------ *
     * Chrome: panels, theme, font size, fullscreen
     * ------------------------------------------------------------------ */

    function togglePanel(name, force) {
        var panel = name === 'toc' ? el.tocPanel : el.markPanel;
        var button = name === 'toc' ? el.tocBtn : el.markBtn;
        var open = typeof force === 'boolean' ? force : panel.hidden;

        panel.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    var THEMES = ['book', 'light', 'sepia', 'dark'];

    function applyTheme(theme) {
        state.theme = theme;
        root.setAttribute('data-theme', theme);
        el.themeBtn.textContent = theme.charAt(0).toUpperCase() + theme.slice(1);
        try {
            window.localStorage.setItem('orp.theme', theme);
        } catch (error) {
            /* storage may be unavailable; the theme still applies */
        }
        relayout();
    }

    function applyFontSize(size) {
        state.fontSize = clamp(size, 0.8, 2);
        root.style.setProperty('--orp-font', (1.0625 * state.fontSize).toFixed(3) + 'rem');
        el.fontLabel.textContent = Math.round(state.fontSize * 100) + '%';
        try {
            window.localStorage.setItem('orp.fontSize', String(state.fontSize));
        } catch (error) {
            /* ignore */
        }
        relayout();
    }

    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            var request = root.requestFullscreen || root.webkitRequestFullscreen || root.msRequestFullscreen;
            if (request) {
                var promise = request.call(root);
                if (promise && promise.catch) {
                    promise.catch(function () {});
                }
            }
        } else {
            var exit = document.exitFullscreen || document.webkitExitFullscreen || document.msExitFullscreen;
            if (exit) {
                exit.call(document);
            }
        }
    }

    /* ------------------------------------------------------------------ *
     * Wiring
     * ------------------------------------------------------------------ */

    el.prev.addEventListener('click', prevPage);
    el.next.addEventListener('click', nextPage);
    if (el.toolPrev) {
        el.toolPrev.addEventListener('click', prevPage);
        el.toolPrev.disabled = true;
    }
    if (el.toolNext) {
        el.toolNext.addEventListener('click', nextPage);
    }
    el.prevChapter.addEventListener('click', previousChapter);
    el.nextChapter.addEventListener('click', nextChapter);

    el.fontIn.addEventListener('click', function () { applyFontSize(state.fontSize + 0.1); });
    el.fontOut.addEventListener('click', function () { applyFontSize(state.fontSize - 0.1); });
    el.themeBtn.addEventListener('click', function () {
        var next = THEMES[(THEMES.indexOf(state.theme) + 1) % THEMES.length];
        applyTheme(next);
    });
    el.fullscreenBtn.addEventListener('click', toggleFullscreen);
    el.tocBtn.addEventListener('click', function () { togglePanel('toc'); });
    el.markBtn.addEventListener('click', function () { togglePanel('marks'); });
    el.markAdd.addEventListener('click', addBookmark);

    // Repaginate when the viewport changes, keeping the reader's place.
    var resizeTimer = null;
    window.addEventListener('resize', function () {
        if (resizeTimer) {
            window.clearTimeout(resizeTimer);
        }
        resizeTimer = window.setTimeout(relayout, 180);
    });

    document.addEventListener('fullscreenchange', function () {
        window.setTimeout(relayout, 120);
    });

    // Crossing the spread breakpoint reflows the book into one page or two.
    if (spreadQuery) {
        var onSpreadChange = function () {
            relayout();
        };
        if (spreadQuery.addEventListener) {
            spreadQuery.addEventListener('change', onSpreadChange);
        } else if (spreadQuery.addListener) {
            spreadQuery.addListener(onSpreadChange);
        }
    }

    document.addEventListener('keydown', function (event) {
        var tag = (event.target && event.target.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || event.target.isContentEditable) {
            return;
        }

        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
            case 'PageDown':
            case ' ':
                event.preventDefault();
                nextPage();
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
            case 'PageUp':
                event.preventDefault();
                prevPage();
                break;
            case 'Home':
                event.preventDefault();
                goToPage(1, true, state.page > 1 ? 'prev' : null);
                break;
            case 'End':
                event.preventDefault();
                goToPage(state.totalPages, true, state.page < state.totalPages ? 'next' : null);
                break;
            case '+':
            case '=':
                event.preventDefault();
                applyFontSize(state.fontSize + 0.1);
                break;
            case '-':
                event.preventDefault();
                applyFontSize(state.fontSize - 0.1);
                break;
            case 'f':
                event.preventDefault();
                toggleFullscreen();
                break;
            case 't':
                event.preventDefault();
                togglePanel('toc');
                break;
            case 'b':
                event.preventDefault();
                addBookmark();
                break;
            case 'Escape':
                if (document.fullscreenElement) {
                    toggleFullscreen();
                } else if (!el.tocPanel.hidden) {
                    togglePanel('toc', false);
                } else if (!el.markPanel.hidden) {
                    togglePanel('marks', false);
                }
                break;
        }
    });

    // Best effort save when the tab goes away.
    window.addEventListener('pagehide', function () {
        var chapter = currentChapter();
        if (!chapter || !state.urls.progress) {
            return;
        }
        var body = new URLSearchParams();
        body.set('chapter_id', chapter.id);
        body.set('page', state.page);
        body.set('percent', readingPercent().toFixed(2));

        if (navigator.sendBeacon) {
            navigator.sendBeacon(state.urls.progress, new Blob([body.toString()], {
                type: 'application/x-www-form-urlencoded; charset=UTF-8'
            }));
        }
    });

    /* ------------------------------------------------------------------ *
     * Boot
     * ------------------------------------------------------------------ */

    // Resume where this customer left off, if we know.
    var startIndex = 0;
    var startPage = 1;
    if (config.progress && config.progress.chapter_id) {
        var resume = state.chapters.findIndex(function (chapter) {
            return chapter.id === config.progress.chapter_id;
        });
        if (resume !== -1) {
            startIndex = resume;
            startPage = clamp(config.progress.page || 1, 1, 100000);
        }
    }

    // Everything below is cosmetic boot (restoring saved UI state). It must
    // never be able to block the chapter load, so it is deliberately fenced
    // off: a throw here previously left "Loading your e-book…" on screen
    // forever because loadChapter() below never executed.
    try {
        var savedTheme = window.localStorage.getItem('orp.theme');
        if (savedTheme && THEMES.indexOf(savedTheme) !== -1) {
            state.theme = savedTheme;
        }
        var savedFont = parseFloat(window.localStorage.getItem('orp.fontSize'));
        if (!isNaN(savedFont) && savedFont > 0) {
            state.fontSize = savedFont;
        }

        root.setAttribute('data-theme', state.theme);
        el.themeBtn.textContent = state.theme.charAt(0).toUpperCase() + state.theme.slice(1);
        applyFontSize(state.fontSize);

        renderBookmarks();
        togglePanel('toc', false);
        togglePanel('marks', false);
    } catch (error) {
        /* cosmetic boot only — the reader still has to start below */
    }

    // A brand-new reader meets the closed front cover; anyone with saved
    // progress deeper into the book lands straight on their page.
    var startCover = startIndex === 0 && startPage <= 1;
    if (el.cover) {
        el.cover.appendChild(buildCoverArt());
    }

    loadChapter(startIndex, startPage, false, startCover);
})();

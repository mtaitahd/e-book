@extends('layouts.customer.app')

@section('title', $purchase->book->title . ' — PDF Reader')

@section('body_class', 'page-reader')

@section('hide_site_chrome', true)

@section('content')
    <div class="reader" id="readerRoot" data-initial-page="{{ $initialPage }}"
         data-src="{{ route('account.purchases.reader-file', $purchase) }}"
         data-progress="{{ route('account.purchases.progress', $purchase) }}">

        {{-- Floating pill toolbar: the reader's only chrome, hovering above
             the book. Left = library/contents, centre = page navigation
             around the title, right = zoom + settings + fullscreen. --}}
        <div class="reader-head">
            <div class="reader-head__side reader-head__side--start">
                <a class="reader-back" href="{{ route('account.purchases.index') }}" aria-label="Back to My Library" title="Back to My Library">
                    <span class="reader-ico" aria-hidden="true">&larr;</span>
                    <span class="reader-lbl">Back</span>
                </a>
                <button type="button" class="reader-tocbtn" id="readerTocBtn" aria-expanded="false" aria-controls="readerToc" hidden title="Contents">
                    <span class="reader-ico" aria-hidden="true">&#9776;</span>
                    <span class="reader-lbl">Contents</span>
                </button>
            </div>

            <div class="reader-head__mid">
                <button type="button" class="reader-ctrl reader-ctrl--turn" id="readerPrev" aria-label="Previous page" title="Previous page">
                    <span class="reader-ico" aria-hidden="true">&lsaquo;</span>
                    <span class="reader-lbl reader-lbl--prev">Previous</span>
                </button>
                <h1 class="reader-title">{{ $purchase->book->title }}</h1>
                <button type="button" class="reader-ctrl reader-ctrl--turn" id="readerNext" aria-label="Next page" title="Next page">
                    <span class="reader-ico" aria-hidden="true">&rsaquo;</span>
                    <span class="reader-lbl reader-lbl--next">Next</span>
                </button>
            </div>

            <div class="reader-head__side reader-head__side--end">
                <span class="reader-zoom-group" role="group" aria-label="Zoom controls">
                    <button type="button" class="reader-ctrl reader-ctrl--sq" id="readerZoomOut" aria-label="Zoom out" title="Zoom out">&minus;</button>
                    <input type="range" class="reader-zoom-slider" id="readerZoomSlider" min="0.5" max="3" step="0.1" value="1" aria-label="Zoom level">
                    <button type="button" class="reader-ctrl reader-ctrl--sq" id="readerZoomIn" aria-label="Zoom in" title="Zoom in">+</button>
                    <button type="button" class="reader-ctrl reader-zoom-pct" id="readerZoomReset" aria-label="Reset zoom to fit width" title="Reset zoom">100%</button>
                </span>
                <button type="button" class="reader-ctrl reader-ctrl--sq" id="readerSettingsBtn" aria-label="Reader settings" aria-expanded="false" aria-controls="readerSettings" title="Settings">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                </button>
                <button type="button" class="reader-expand" id="readerFullscreenTop" aria-label="Enter fullscreen" aria-pressed="false" title="Fullscreen">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                </button>
            </div>
        </div>

        <div class="reader-stage" id="readerStage">
            <div class="reader-loading" id="readerLoading" role="status" aria-live="polite">
                <span class="reader-spinner" aria-hidden="true"></span>
                <p>Loading your book&hellip;</p>
            </div>

            <div class="reader-message reader-error" id="readerError" role="alert" hidden>
                <p id="readerErrorText">Sorry, this PDF could not be opened right now. Please check again later.</p>
                <p><a href="{{ route('account.purchases.index') }}">Back to My Library</a></p>
            </div>

            {{-- The scroll container doubles as the fit measurer; the open book
                 inside is centred with auto margins so zoomed content stays
                 scrollable from the top-left corner. --}}
            <div class="reader-canvas-wrap" id="readerScroll">
                <div class="bkrd-book bkrd-book--single" id="bkrdBook">
                    <div class="bkrd-page bkrd-page--l">
                        <img class="reader-canvas bkrd-img" id="bkrdImgL" alt="Left page" draggable="false" hidden>
                    </div>
                    <div class="bkrd-page bkrd-page--r">
                        <img class="reader-canvas bkrd-img" id="bkrdImgR" alt="Right page" draggable="false" hidden>
                    </div>
                    <span class="bkrd-gutter" aria-hidden="true"></span>
                    <div class="bkrd-leaf" id="bkrdLeaf" hidden>
                        <div class="bkrd-leaf__side bkrd-leaf__front">
                            <img class="reader-canvas bkrd-img" id="bkrdLeafFront" alt="" draggable="false" hidden>
                            <span class="bkrd-leaf__sh" id="bkrdShadeF" aria-hidden="true"></span>
                        </div>
                        <div class="bkrd-leaf__side bkrd-leaf__back">
                            <img class="reader-canvas bkrd-img" id="bkrdLeafBack" alt="" draggable="false" hidden>
                            <span class="bkrd-leaf__sh" id="bkrdShadeB" aria-hidden="true"></span>
                        </div>
                    </div>
                </div>
            </div>

            <nav class="reader-toc" id="readerToc" hidden aria-label="Table of contents">
                <div class="reader-toc__head">
                    <strong>Contents</strong>
                    <button type="button" class="reader-toc__close" id="readerTocClose" aria-label="Close contents">&times;</button>
                </div>
                <ol class="reader-toc__list" id="readerTocList"></ol>
            </nav>

            <p class="reader-notice" id="readerNotice" role="status" hidden>
                <span id="readerNoticeText"></span>
            </p>
        </div>

        {{-- Floating bottom pill: quiet reading status — page, progress, percent. --}}
        <div class="reader-bar">
            <div class="reader-bar__group reader-bar__group--nav">
                <span class="reader-bar__ico" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                </span>
                <div class="reader-pageno" id="readerPageNo" role="status" aria-live="polite" aria-label="Current page">
                    Page <span id="readerCurrent">1</span> of <span id="readerTotal">&hellip;</span>
                </div>
                <span class="reader-save" id="readerSave" role="status" aria-live="polite"></span>
            </div>

            <div class="reader-bar__group reader-bar__group--center">
                <span class="reader-progress" aria-hidden="true">
                    <span class="reader-progress__fill" id="readerProgressFill"></span>
                </span>
            </div>

            <div class="reader-bar__group reader-bar__group--right">
                <span class="reader-lbl reader-bar__pct" id="readerProgressPct">0%</span>
                <button type="button" class="reader-ctrl reader-ctrl--sq" id="readerFullscreen" aria-label="Enter fullscreen" aria-pressed="false" title="Fullscreen">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
                    <span class="reader-lbl" id="readerFsLabel">Fullscreen</span>
                </button>
            </div>
        </div>

        <div class="reader-settings" id="readerSettings" hidden>
            <div class="reader-settings__row">
                <label class="reader-settings__label" for="readerJump">Go to page</label>
                <input class="reader-jump" type="number" id="readerJump" min="1" value="1">
                <button type="button" class="reader-ctrl reader-ctrl--sm" id="readerJumpGo">Go</button>
            </div>
            <div class="reader-settings__row reader-settings__row--views" role="radiogroup" aria-label="Page layout">
                <label><input type="radio" name="bkrView" class="reader-view-input" value="auto" checked> Auto</label>
                <label><input type="radio" name="bkrView" class="reader-view-input" value="1"> One page</label>
                <label><input type="radio" name="bkrView" class="reader-view-input" value="2"> Two pages</label>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/book-reader.css') }}">
    <style>
        /* ---- Focus mode: the reader owns the whole viewport ---- */
        body.page-reader { overflow: hidden; }
        body.page-reader { background: #0a101a; }
        .page-reader .ebs-header,
        .page-reader .site-footer { display: none; }
        .page-reader .site-main { padding: 0; }
        .page-reader .container { max-width: none; padding: 0; }

        /* ---- Reading environment: edge-to-edge dark shell with subtle depth ---- */
        .reader {
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            background:
                radial-gradient(1100px 620px at 50% -6%, rgba(56, 74, 104, .40), transparent 62%),
                radial-gradient(920px 600px at 50% 107%, rgba(40, 55, 84, .38), transparent 58%),
                linear-gradient(180deg, #0e1524 0%, #0b111d 55%, #0a101a 100%);
        }

        /* ---- Floating pill toolbar ---- */
        .reader-head {
            position: fixed;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 120;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 10px;
            width: max-content;
            max-width: min(880px, calc(100vw - 28px));
            min-height: 52px;
            padding: 7px 10px;
            background: rgba(15, 23, 42, .95);
            -webkit-backdrop-filter: blur(14px);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 999px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, .48), 0 3px 10px rgba(0, 0, 0, .4);
        }
        .reader-head__mid {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            min-width: 0;
        }

        .reader-back {
            justify-self: start;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 34px;
            padding: 0 10px;
            background: transparent;
            border: 1px solid transparent;
            color: #cbd5e1;
            border-radius: 999px;
            font-size: .88rem;
            font-weight: var(--ebs-fw-ui);
            white-space: nowrap;
            transition: background .15s ease, border-color .15s ease, color .15s ease;
        }
        .reader-back:hover { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .16); color: #fff; text-decoration: none; }
        .reader-back:focus-visible { outline: 2px solid var(--ebs-orange); outline-offset: 2px; }
        .reader-title { margin: 0; justify-self: center; max-width: min(46vw, 420px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #f1f5f9; font-size: .98rem; font-weight: var(--ebs-fw-display); letter-spacing: .01em; }
        .reader-expand { justify-self: end; flex: none; width: 34px; height: 34px; border-radius: 999px; background: transparent; color: #cbd5e1; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background .15s ease, border-color .15s ease, color .15s ease; }
        .reader-expand:hover { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .16); color: #fff; }
        .reader-expand:focus-visible { outline: 2px solid var(--ebs-orange); outline-offset: 2px; }

        /* Controls stay labelled on desktop; mobile hides the text, keeps icons.
           The turn buttons are chevron-only: they flank the title in the pill
           centre, and their names live in aria-label + title. */
        .reader-lbl { font-size: .86rem; letter-spacing: .01em; }
        .reader-ctrl--turn .reader-lbl { display: none; }
        .reader-ico { display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; line-height: 1; }

        /* ---- Document area: the only thing that scrolls ---- */
        .reader-stage { position: relative; flex: 1 1 auto; min-height: 0; display: flex; align-items: stretch; justify-content: center; padding: 84px 16px 96px; }
        .reader-canvas-wrap { width: 95%; max-width: 1400px; height: 100%; margin: 0 auto; overflow-y: auto; overflow-x: auto; -webkit-overflow-scrolling: touch; padding: 18px; display: flex; touch-action: pan-y; }
        .reader-canvas { display: block; margin: 0 auto; background: #f8f5ec; box-shadow: 0 16px 40px rgba(0, 0, 0, .5); }

        .reader-loading { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; color: #fff; z-index: 2; }
        .reader-loading[hidden] { display: none; }
        .reader-spinner { width: 34px; height: 34px; border-radius: 50%; border: 3px solid rgba(255, 255, 255, .25); border-top-color: var(--ebs-orange); animation: reader-spin .8s linear infinite; }
        @keyframes reader-spin { to { transform: rotate(360deg); } }

        .reader-message { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: #fff; text-align: center; padding: 24px; z-index: 2; }
        .reader-message[hidden] { display: none; }
        .reader-message p { color: rgba(255, 255, 255, .88); margin: 0; }
        .reader-message a { color: var(--ebs-search); font-weight: var(--ebs-fw-ui); }

        /* ---- Native-viewer fallback: browser PDF plugin inside the stage ---- */
        .reader-nativepdf { display: block; width: 100%; height: 100%; min-height: 70vh; border: 0; background: #fff; border-radius: 7px; box-shadow: 0 24px 60px rgba(0, 0, 0, .55); }
        .reader-notice { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); z-index: 3; max-width: 90%; margin: 0; padding: 7px 15px; background: rgba(15, 23, 42, .94); border: 1px solid rgba(255, 255, 255, .16); border-radius: 999px; color: #e2e8f0; font-size: .82rem; text-align: center; }
        .reader-notice[hidden] { display: none; }

        /* ---- Floating bottom progress pill ---- */
        .reader-bar {
            position: fixed;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 120;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 14px;
            width: max-content;
            max-width: calc(100vw - 24px);
            padding: 8px 14px;
            background: rgba(15, 23, 42, .95);
            -webkit-backdrop-filter: blur(8px);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, .12);
            border-radius: 999px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, .45);
        }
        .reader-bar__group { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .reader-bar__group--nav { justify-content: flex-start; }
        .reader-bar__group--center { justify-content: center; }
        .reader-bar__group--right { justify-content: flex-end; gap: 6px; }
        .reader-bar__ico { display: inline-flex; color: #94a3b8; }
        .reader-bar__pct { position: static; width: auto; height: auto; margin: 0; overflow: visible; clip: auto; border: 0; font-size: .74rem; font-weight: 600; color: #94a3b8; white-space: nowrap; }

        .reader-pageno { color: #e2e8f0; font-weight: var(--ebs-fw-ui); font-size: .78rem; letter-spacing: .02em; white-space: nowrap; }

        /* ---- Compact pill controls ---- */
        .reader-ctrl { display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 34px; min-width: 34px; padding: 0 10px; background: transparent; border: 1px solid transparent; color: #cbd5e1; border-radius: 999px; font-weight: var(--ebs-fw-ui); font-size: .86rem; white-space: nowrap; cursor: pointer; transition: background .15s ease, border-color .15s ease, color .15s ease; }
        .reader-ctrl:hover:not(:disabled) { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .16); color: #fff; }
        .reader-ctrl:focus-visible { outline: 2px solid var(--ebs-orange); outline-offset: 2px; }
        .reader-ctrl:disabled { opacity: .35; cursor: not-allowed; }
        .reader-ctrl--sq { padding: 0 8px; font-size: 1rem; }
        .reader-ctrl--turn { width: 34px; padding: 0; color: #e2e8f0; }
        .reader-ctrl--turn .reader-ico { font-size: 1.35rem; }
        .reader-ctrl--primary { background: rgba(255, 255, 255, .08); border-color: rgba(255, 255, 255, .14); color: #f1f5f9; }
        .reader-ctrl--primary:hover:not(:disabled) { background: rgba(255, 255, 255, .16); border-color: rgba(255, 255, 255, .28); color: #fff; }

        .reader-zoom-group { display: inline-flex; align-items: center; gap: 4px; }
        .reader-zoom-pct { min-width: 52px; justify-content: center; font-size: .78rem; }
        .reader-zoom-slider { -webkit-appearance: none; appearance: none; width: 92px; height: 4px; border-radius: 999px; background: rgba(255, 255, 255, .25); cursor: pointer; }
        .reader-zoom-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 15px; height: 15px; border-radius: 50%; background: var(--ebs-orange); border: none; cursor: pointer; }
        .reader-zoom-slider::-moz-range-thumb { width: 15px; height: 15px; border-radius: 50%; background: var(--ebs-orange); border: none; cursor: pointer; }
        .reader-zoom-slider:focus-visible { outline: 2px solid var(--ebs-orange); outline-offset: 4px; }
        .reader-zoom-slider:disabled { opacity: .4; cursor: not-allowed; }

        /* ---- Native fullscreen shares the focus layout ---- */
        .reader:fullscreen,
        .reader:-webkit-full-screen { width: 100%; height: 100%; }
        .reader:fullscreen::backdrop,
        .reader:-webkit-full-screen::backdrop { background: #0a101a; }
        .reader:fullscreen .reader-back,
        .reader:-webkit-full-screen .reader-back { visibility: hidden; }
        .reader--immersive .reader-back { visibility: hidden; }

        /* ---- Mobile: compact pill, edge-to-edge book ---- */
        @media (max-width: 768px) {
            .reader-head { top: 10px; height: 50px; min-height: 50px; padding: 5px 8px; gap: 6px; max-width: calc(100vw - 18px); }
            .reader-title { font-size: .88rem; }
            .reader-back { padding: 0 8px; }
            .reader-back .reader-lbl { display: none; }
            .reader-head .reader-lbl { display: none; }
            .reader-stage { padding: 0; }
            .reader-canvas-wrap { width: 100%; max-width: none; box-sizing: border-box; padding: 66px 10px 76px; }
            .reader-bar { grid-template-columns: auto 1fr auto; gap: 8px; padding: 7px 12px; bottom: 12px; max-width: calc(100vw - 16px); }
            .reader-bar .reader-lbl { display: none; }
            .reader-zoom-slider { display: none; }
            .reader-zoom-pct { display: none; }
            .reader-ctrl { height: 32px; min-width: 32px; padding: 0 8px; font-size: .84rem; }
            .reader-ctrl--turn { width: 32px; padding: 0; }
            .reader-pageno { font-size: .74rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .reader-spinner { animation: none; }
            .reader-ctrl,
            .reader-back,
            .reader-expand { transition: none; }
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/reader/pdfjs/pdf.min.js') }}"></script>
    <script>
        (function () {
            var pdfjsLib = window.pdfjsLib;
            var stage = document.getElementById('readerStage');
            var scrollEl = document.getElementById('readerScroll');
            var bookEl = document.getElementById('bkrdBook');
            var imgL = document.getElementById('bkrdImgL');
            var imgR = document.getElementById('bkrdImgR');
            var leaf = document.getElementById('bkrdLeaf');
            var leafF = document.getElementById('bkrdLeafFront');
            var leafB = document.getElementById('bkrdLeafBack');
            var shF = document.getElementById('bkrdShadeF');
            var shB = document.getElementById('bkrdShadeB');
            var loadingEl = document.getElementById('readerLoading');
            var errorEl = document.getElementById('readerError');
            var errorTextEl = document.getElementById('readerErrorText');
            var currentEl = document.getElementById('readerCurrent');
            var totalEl = document.getElementById('readerTotal');
            var prevBtn = document.getElementById('readerPrev');
            var nextBtn = document.getElementById('readerNext');
            var zoomInBtn = document.getElementById('readerZoomIn');
            var zoomOutBtn = document.getElementById('readerZoomOut');
            var zoomResetBtn = document.getElementById('readerZoomReset');
            var zoomSlider = document.getElementById('readerZoomSlider');
            var fullscreenBtn = document.getElementById('readerFullscreen');
            var fullscreenTopBtn = document.getElementById('readerFullscreenTop');
            var fullscreenLabel = document.getElementById('readerFsLabel');
            var noticeEl = document.getElementById('readerNotice');
            var noticeTextEl = document.getElementById('readerNoticeText');
            var tocBtn = document.getElementById('readerTocBtn');
            var tocEl = document.getElementById('readerToc');
            var tocList = document.getElementById('readerTocList');
            var tocClose = document.getElementById('readerTocClose');
            var settingsBtn = document.getElementById('readerSettingsBtn');
            var settingsEl = document.getElementById('readerSettings');
            var jumpInput = document.getElementById('readerJump');
            var jumpGo = document.getElementById('readerJumpGo');
            var progressFill = document.getElementById('readerProgressFill');
            var progressPct = document.getElementById('readerProgressPct');
            var saveEl = document.getElementById('readerSave');
            var viewInputs = document.querySelectorAll('.reader-view-input');

            if (!stage || !scrollEl || !bookEl) {
                // The page markup itself is broken; there is nothing to fall back to.
                if (errorTextEl) errorTextEl.textContent = 'The PDF reader could not start. Refresh the page and try again.';
                if (errorEl) errorEl.hidden = false;
                if (loadingEl) loadingEl.hidden = true;
                return;
            }

            var root = document.querySelector('.reader');
            var csrfMeta = document.querySelector('meta[name="csrf-token"]');
            var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
            var initialPage = parseInt(root && root.getAttribute('data-initial-page'), 10) || 1;
            var config = {
                url: root.getAttribute('data-src'),
                progressUrl: root.getAttribute('data-progress'),
                csrf: csrfToken,
                initialPage: initialPage
            };

            if (pdfjsLib) {
                pdfjsLib.GlobalWorkerOptions.workerSrc = '{{ asset('assets/reader/pdfjs/pdf.worker.min.js') }}';
            }

            var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            var FLIP_MS = reduceMotion ? 0 : 460;

            var state = {
                pdf: null,
                numPages: 0,
                current: 1,
                mode: 'single',
                zoom: 1,
                rendering: false,
                native: false,
                aspect: 1.35,
                pageW: 0,
                pageH: 0,
                dispW: 0,
                dispH: 0,
                bucket: 0,
                flipping: false,
                pref: 'auto'
            };

            try {
                var storedPref = window.localStorage.getItem('bkr.view');
                if (storedPref === '1' || storedPref === '2' || storedPref === 'auto') state.pref = storedPref;
            } catch (prefErr) { /* storage unavailable */ }

            /* ------------------------------------------------------------------
             * Render cache: page number + pixel bucket -> JPEG data URL.
             * Keeps flips instant (pages are pre-rendered around the spread)
             * while bounding memory with a small LRU.
             * ---------------------------------------------------------------- */
            var cache = new Map();
            var pending = new Map();
            var renderChain = Promise.resolve();

            function trimCache() {
                while (cache.size > 48) {
                    var oldest = cache.keys().next().value;
                    cache.delete(oldest);
                }
            }

            function touchKey(key) {
                var value = cache.get(key);
                cache.delete(key);
                cache.set(key, value);
            }

            function renderOne(n) {
                var key = n + '@' + state.bucket;
                if (cache.has(key)) {
                    touchKey(key);
                    return Promise.resolve(cache.get(key));
                }
                if (pending.has(key)) return pending.get(key);

                var bucketAtStart = state.bucket;
                var job = renderChain.then(function () {
                    if (!state.pdf) return null;
                    return state.pdf.getPage(n).then(function (page) {
                        var viewport1 = page.getViewport({ scale: 1 });
                        var scale = bucketAtStart / viewport1.width;
                        var viewport = page.getViewport({ scale: scale });
                        var canvas = document.createElement('canvas');
                        canvas.width = Math.max(1, Math.floor(viewport.width));
                        canvas.height = Math.max(1, Math.floor(viewport.height));
                        var ctx = canvas.getContext('2d');
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, canvas.width, canvas.height);
                        var renderTask = page.render({ canvasContext: ctx, viewport: viewport });
                        return renderTask.promise.then(function () {
                            return canvas.toDataURL('image/jpeg', 0.93);
                        });
                    }).then(function (url) {
                        if (url && bucketAtStart === state.bucket) {
                            cache.set(key, url);
                            trimCache();
                        }
                        return url;
                    });
                }).catch(function () {
                    return null;
                });

                renderChain = job;
                pending.set(key, job);
                job.then(function () { pending.delete(key); });
                return job;
            }

            function ensure(pages) {
                var wanted = [];
                pages.forEach(function (p) {
                    if (p >= 1 && p <= state.numPages && wanted.indexOf(p) === -1) wanted.push(p);
                });
                return Promise.all(wanted.map(renderOne));
            }

            /* ------------------------------------------------------------------
             * Painting: cached URLs land synchronously (flip frames need this),
             * everything else arrives async with a per-image token guard.
             * ---------------------------------------------------------------- */
            function blankImage(img) {
                img.hidden = true;
                img.removeAttribute('src');
                delete img.dataset.page;
            }

            function paintCached(img, n) {
                if (!n || n < 1 || n > state.numPages) {
                    blankImage(img);
                    return false;
                }
                var key = n + '@' + state.bucket;
                if (!cache.has(key)) return false;
                touchKey(key);
                img.dataset.page = String(n);
                img.src = cache.get(key);
                img.hidden = false;
                return true;
            }

            function showPage(img, n) {
                if (!n || n < 1 || n > state.numPages) {
                    blankImage(img);
                    return;
                }
                if (paintCached(img, n)) return;
                var wantBucket = state.bucket;
                var wantPage = n;
                renderOne(n).then(function (url) {
                    if (!url || wantBucket !== state.bucket) return;
                    if (img.dataset.page !== undefined && img.dataset.page !== String(wantPage)) return;
                    img.dataset.page = String(wantPage);
                    img.src = url;
                    img.hidden = false;
                });
            }

            /* ------------------------------------------------------------------
             * Layout: fit the spread inside the scroll container, then pick the
             * render bucket (device-pixel aware) for the visible pages.
             * ---------------------------------------------------------------- */
            function decideMode() {
                var two = false;
                if (state.numPages >= 2) {
                    if (state.pref === '2') two = true;
                    else if (state.pref === '1') two = false;
                    else two = scrollEl.clientWidth >= 560;
                }
                state.mode = two ? 'two' : 'single';
                bookEl.classList.toggle('bkrd-book--single', !two);
                bookEl.classList.toggle('bkrd-book--two', two);
            }

            function fitSize() {
                var styles = window.getComputedStyle(scrollEl);
                var padX = (parseFloat(styles.paddingLeft) || 0) + (parseFloat(styles.paddingRight) || 0);
                var padY = (parseFloat(styles.paddingTop) || 0) + (parseFloat(styles.paddingBottom) || 0);
                var availW = Math.max(scrollEl.clientWidth - padX, 240);
                var availH = Math.max(scrollEl.clientHeight - padY, 200);
                var cols = state.mode === 'two' ? 2 : 1;

                var pw = availW / cols;
                var ph = pw * state.aspect;
                if (ph > availH) {
                    ph = availH;
                    pw = ph / state.aspect;
                }

                state.pageW = pw;
                state.pageH = ph;
                state.dispW = Math.max(1, Math.round(pw * state.zoom));
                state.dispH = Math.max(1, Math.round(ph * state.zoom));
                bookEl.style.setProperty('--bkrd-pw', state.dispW + 'px');
                bookEl.style.setProperty('--bkrd-ph', state.dispH + 'px');

                var dpr = Math.min(window.devicePixelRatio || 1, 2);
                var bucket = Math.round(state.dispW * dpr / 100) * 100;
                return Math.max(320, Math.min(2400, bucket));
            }

            function normalize(n) {
                n = Math.min(Math.max(1, n || 1), Math.max(1, state.numPages));
                if (state.mode === 'two' && n % 2 === 0 && n > 1) n = n - 1;
                return n;
            }

            function canPrev() {
                if (!state.numPages) return false;
                if (state.mode === 'two') return state.current >= 3;
                return state.current > 1;
            }

            function canNext() {
                if (!state.numPages) return false;
                if (state.mode === 'two') return state.current + 2 <= state.numPages;
                return state.current < state.numPages;
            }

            function visiblePages() {
                if (state.mode === 'two') {
                    return [state.current, state.current + 1 <= state.numPages ? state.current + 1 : 0];
                }
                return [state.current, 0];
            }

            function paintVisible() {
                var vis = visiblePages();
                showPage(imgL, vis[0]);
                showPage(imgR, vis[1]);
            }

            function updateControls() {
                if (!state.numPages) return;
                var two = state.mode === 'two';
                var rightPage = two ? state.current + 1 : state.current;
                if (two && state.current !== rightPage && rightPage <= state.numPages) {
                    currentEl.textContent = state.current + '–' + rightPage;
                } else {
                    currentEl.textContent = String(state.current);
                }
                totalEl.textContent = state.numPages;
                prevBtn.disabled = state.flipping || !state.pdf || !canPrev();
                nextBtn.disabled = state.flipping || !state.pdf || !canNext();
                zoomResetBtn.textContent = Math.round(state.zoom * 100) + '%';
                if (zoomSlider) zoomSlider.value = String(state.zoom);
                if (jumpInput && document.activeElement !== jumpInput) jumpInput.value = String(state.current);

                var shown = two ? Math.min(state.current + 1, state.numPages) : state.current;
                var pct = state.numPages > 1
                    ? Math.round((shown - 1) / (state.numPages - 1) * 100)
                    : 100;
                if (progressFill) progressFill.style.width = pct + '%';
                if (progressPct) progressPct.textContent = pct + '%';
            }

            function setBusy(busy) {
                [prevBtn, nextBtn, zoomInBtn, zoomOutBtn, zoomResetBtn, zoomSlider].forEach(function (btn) {
                    if (btn) btn.disabled = busy;
                });
            }

            function showLoading() { if (loadingEl) loadingEl.hidden = false; if (errorEl) errorEl.hidden = true; }
            function hideLoading() { if (loadingEl) loadingEl.hidden = true; }
            function showError(message) {
                if (errorTextEl && message) errorTextEl.textContent = message;
                if (errorEl) errorEl.hidden = false;
                hideLoading();
            }

            /* ------------------------------------------------------------------
             * Progress: the right-hand page of the spread is what we persist,
             * debounced while flipping and flushed when the tab goes away.
             * ---------------------------------------------------------------- */
            function progressPage() {
                if (!state.numPages) return 0;
                return state.mode === 'two'
                    ? Math.min(state.current + 1, state.numPages)
                    : state.current;
            }

            function saveProgress(pageNumber) {
                if (!config.csrf || pageNumber < 1) return;
                fetch(config.progressUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrf
                    },
                    credentials: 'same-origin',
                    keepalive: true,
                    body: JSON.stringify({ current_page: pageNumber })
                }).then(function () {
                    if (saveEl) saveEl.textContent = 'Saved';
                }).catch(function () { /* progress is best-effort, never blocking */ });
            }

            var saveTimer = null;
            function scheduleProgress() {
                if (!config.csrf || !state.numPages) return;
                if (saveEl) saveEl.textContent = 'Saving…';
                if (saveTimer) window.clearTimeout(saveTimer);
                saveTimer = window.setTimeout(function () {
                    saveTimer = null;
                    saveProgress(progressPage());
                }, 600);
            }

            /* ------------------------------------------------------------------
             * Preloading: keep the pages around the spread warm so turns never
             * wait on the renderer in normal reading.
             * ---------------------------------------------------------------- */
            function preload() {
                if (!state.pdf || state.native) return;
                var from = state.mode === 'two' ? state.current - 3 : state.current - 3;
                var to = state.mode === 'two' ? state.current + 4 : state.current + 3;
                var list = [];
                for (var i = from; i <= to; i++) list.push(i);
                ensure(list);
            }

            function setPage(number) {
                if (!state.pdf || state.native) return;
                state.current = normalize(number);
                paintVisible();
                updateControls();
                scheduleProgress();
                preload();
            }

            /* ------------------------------------------------------------------
             * Page-turn animation.
             *
             * Two-page spread, anchor N (left page):
             *   next: leaf [N+1 front | N+2 back] rotates 0 -> -180 around the
             *         gutter; base shows [N | N+3] mid-flip; ends [N+2 | N+3].
             *   prev: leaf [M-1 front | M back] rotates -180 -> 0; base shows
             *         [M-2 | M+1] mid-flip; ends [M-2 | M-1].
             * Single page: the leaf peels around the left edge in place; the
             * incoming page is revealed underneath, so the commit is seamless.
             * ---------------------------------------------------------------- */
            function easeInOutFlip(p) {
                return p < 0.5 ? 4 * p * p * p : 1 - Math.pow(-2 * p + 2, 3) / 2;
            }

            function hideLeaf() {
                leaf.hidden = true;
                leaf.style.transform = '';
                leaf.style.opacity = '';
                if (shF) shF.style.opacity = '0';
                if (shB) shB.style.opacity = '0';
                scrollEl.classList.remove('bkrd-flipping');
            }

            function runFlip(two, dir, done) {
                leaf.hidden = false;
                var startAngle = dir === 'prev' ? -180 : 0;
                leaf.style.opacity = two ? '1' : (dir === 'prev' ? '0' : '1');
                leaf.style.transform = 'rotateY(' + startAngle + 'deg)';

                if (FLIP_MS <= 0) {
                    done();
                    return;
                }

                scrollEl.classList.add('bkrd-flipping');
                var start = 0;

                function frame(now) {
                    if (!start) start = now;
                    var raw = Math.min(1, (now - start) / FLIP_MS);
                    var p = easeInOutFlip(raw);
                    var angle = dir === 'next' ? -180 * p : -180 * (1 - p);
                    leaf.style.transform = 'rotateY(' + angle + 'deg)';

                    var shade = 0.5 * Math.sin(Math.PI * p);
                    if (shF) shF.style.opacity = String(shade);
                    if (shB) shB.style.opacity = String(shade);

                    if (!two) {
                        var opacity;
                        if (dir === 'next') {
                            opacity = p > 0.76 ? Math.max(0, 1 - (p - 0.76) / 0.24) : 1;
                        } else {
                            opacity = p < 0.24 ? Math.max(0, p / 0.24) : 1;
                        }
                        leaf.style.opacity = String(opacity);
                    }

                    if (raw < 1) {
                        window.requestAnimationFrame(frame);
                    } else {
                        scrollEl.classList.remove('bkrd-flipping');
                        done();
                    }
                }

                window.requestAnimationFrame(frame);
            }

            function turn(dir) {
                if (!state.pdf || state.native || state.flipping) return;

                var two = state.mode === 'two';
                var cur = state.current;
                var n = state.numPages;
                var leafFront, leafBack, baseL, baseR, end;

                if (two) {
                    if (dir === 'next') {
                        if (cur + 2 > n) return;
                        leafFront = cur + 1;
                        leafBack = cur + 2;
                        baseL = cur;
                        baseR = cur + 3;
                        end = cur + 2;
                    } else {
                        if (cur < 3) return;
                        leafFront = cur - 1;
                        leafBack = cur;
                        baseL = cur - 2;
                        baseR = cur + 1;
                        end = cur - 2;
                    }
                } else {
                    if (dir === 'next') {
                        if (cur >= n) return;
                        leafFront = cur;
                        leafBack = cur + 1;
                        baseL = cur + 1;
                        end = cur + 1;
                    } else {
                        if (cur <= 1) return;
                        leafFront = cur - 1;
                        leafBack = cur;
                        baseL = cur;
                        end = cur - 1;
                    }
                }

                var needed = [leafFront, leafBack, baseL];
                if (two) needed.push(baseR);

                state.flipping = true;
                updateControls();

                ensure(needed).then(function () {
                    if (state.native) {
                        state.flipping = false;
                        updateControls();
                        return;
                    }

                    if (two) {
                        paintCached(imgL, baseL);
                        paintCached(imgR, baseR <= n ? baseR : 0);
                    } else {
                        paintCached(imgL, baseL);
                    }
                    var okFront = paintCached(leafF, leafFront);
                    var okBack = paintCached(leafB, leafBack);

                    if (!okFront || !okBack) {
                        // A page failed to render: land on the target spread
                        // directly rather than animating a stale leaf.
                        state.flipping = false;
                        setPage(end);
                        return;
                    }

                    runFlip(two, dir, function () {
                        state.current = end;
                        var vis = visiblePages();
                        paintCached(imgL, vis[0]);
                        paintCached(imgR, vis[1]);
                        hideLeaf();
                        state.flipping = false;
                        updateControls();
                        scheduleProgress();
                        preload();
                    });
                }, function () {
                    state.flipping = false;
                    updateControls();
                });
            }

            function goPrev() { turn('prev'); }
            function goNext() { turn('next'); }

            /* ------------------------------------------------------------------
             * Zoom & refit.
             * ---------------------------------------------------------------- */
            function renderPage(number) {
                if (state.native || !state.pdf) return;
                state.rendering = true;
                var newBucket = fitSize();
                var bucketChanged = newBucket !== state.bucket;
                state.bucket = newBucket;
                state.current = normalize(typeof number === 'number' ? number : state.current);
                paintVisible();
                updateControls();
                if (bucketChanged) preload();
                state.rendering = false;
            }

            function applyZoom(value) {
                state.zoom = Math.min(3, Math.max(0.5, Math.round(value * 100) / 100));
                updateControls();
                if (state.pdf) renderPage(state.current);
            }

            function zoomBy(delta) {
                applyZoom(state.zoom + delta);
            }

            function relayout() {
                if (!state.pdf) return;
                decideMode();
                state.current = normalize(state.current);
                state.bucket = fitSize();
                paintVisible();
                updateControls();
                preload();
            }

            /* ------------------------------------------------------------------
             * Native-viewer fallback: hand the stage to the browser's built-in
             * PDF viewer pointed at the same authorised URL. The spread reader
             * stays primary; this only appears when pdf.js cannot start or
             * cannot read the file, so a customer is never left staring at a
             * dead loading screen.
             * ---------------------------------------------------------------- */
            function openNativeViewer(reason) {
                if (state.native) return;
                state.native = true;
                hideLoading();
                if (errorEl) errorEl.hidden = true;
                bookEl.hidden = true;
                scrollEl.style.overflow = 'hidden';
                setBusy(true);
                if (noticeTextEl) noticeTextEl.textContent = reason;
                if (noticeEl) noticeEl.hidden = false;
                var frame = document.createElement('iframe');
                frame.className = 'reader-nativepdf';
                frame.title = 'PDF viewer';
                frame.src = config.url;
                scrollEl.appendChild(frame);
            }

            if (!pdfjsLib) {
                openNativeViewer('The advanced PDF reader is unavailable — opened with your browser’s built-in viewer.');
            }

            /* ------------------------------------------------------------------
             * Table of contents (built only when the PDF carries an outline).
             * ---------------------------------------------------------------- */
            function closeToc() {
                if (tocEl) tocEl.hidden = true;
                if (tocBtn) tocBtn.setAttribute('aria-expanded', 'false');
            }

            function openToc() {
                if (tocEl) tocEl.hidden = false;
                if (tocBtn) tocBtn.setAttribute('aria-expanded', 'true');
            }

            function resolveDestPage(pdf, dest) {
                var lookup = typeof dest === 'string' ? pdf.getDestination(dest) : Promise.resolve(dest);
                return lookup.then(function (destArr) {
                    if (!destArr || !destArr.length || !destArr[0] || typeof destArr[0].num !== 'number') {
                        return 0;
                    }
                    return pdf.getPageIndex(destArr[0]).then(function (index) { return index + 1; });
                }).catch(function () { return 0; });
            }

            function buildToc(pdf) {
                if (!pdf.getOutline || !tocBtn || !tocList) return;
                pdf.getOutline().then(function (outline) {
                    if (!outline || !outline.length) return;
                    tocBtn.hidden = false;
                    var entries = [];
                    var chain = Promise.resolve();
                    outline.forEach(function (item) {
                        chain = chain.then(function () {
                            return resolveDestPage(pdf, item.dest).then(function (page) {
                                if (page) entries.push({ title: item.title || ('Page ' + page), page: page, bold: !!item.bold });
                            });
                        });
                    });
                    return chain.then(function () {
                        tocList.innerHTML = '';
                        entries.forEach(function (entry) {
                            var li = document.createElement('li');
                            var button = document.createElement('button');
                            button.type = 'button';
                            button.className = 'reader-toc__item';
                            button.textContent = entry.title;
                            if (entry.bold) button.style.fontWeight = '700';
                            button.addEventListener('click', function () {
                                setPage(entry.page);
                                closeToc();
                            });
                            li.appendChild(button);
                            tocList.appendChild(li);
                        });
                    });
                }).catch(function () { /* outline is optional */ });
            }

            /* ------------------------------------------------------------------
             * Settings popover: jump-to-page + layout preference.
             * ---------------------------------------------------------------- */
            function closeSettings() {
                if (settingsEl) settingsEl.hidden = true;
                if (settingsBtn) settingsBtn.setAttribute('aria-expanded', 'false');
            }

            function openSettings() {
                if (settingsEl) settingsEl.hidden = false;
                if (settingsBtn) settingsBtn.setAttribute('aria-expanded', 'true');
                if (jumpInput) {
                    jumpInput.value = String(state.current);
                    jumpInput.focus();
                    jumpInput.select();
                }
            }

            function jumpToPage() {
                var target = parseInt(jumpInput && jumpInput.value, 10);
                if (!target || !state.numPages) { closeSettings(); return; }
                setPage(target);
                closeSettings();
            }

            /* ------------------------------------------------------------------
             * Fullscreen.
             * ---------------------------------------------------------------- */
            function isFullscreen() {
                return !!document.fullscreenElement;
            }

            function syncFullscreenUi() {
                var on = isFullscreen();
                [fullscreenBtn, fullscreenTopBtn].forEach(function (btn) {
                    if (!btn) return;
                    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                    btn.setAttribute('aria-label', on ? 'Exit fullscreen' : 'Enter fullscreen');
                });
                if (fullscreenLabel) fullscreenLabel.textContent = on ? 'Exit' : 'Fullscreen';
            }

            function toggleFullscreen() {
                if (isFullscreen()) {
                    var exit = document.exitFullscreen || document.webkitExitFullscreen;
                    if (exit) exit.call(document);
                    return;
                }

                var rfs = root.requestFullscreen || root.webkitRequestFullscreen;
                if (rfs) {
                    var p = rfs.call(root);
                    if (p && p.catch) p.catch(function () { root.classList.add('reader--immersive'); syncFullscreenUi(); });
                } else {
                    root.classList.add('reader--immersive');
                    syncFullscreenUi();
                }
            }

            /* ------------------------------------------------------------------
             * Opening the document: fetch the protected PDF once, then hand its
             * bytes to PDF.js. XAMPP/Apache deployments can reject or stall
             * PDF.js Range requests even when the authorised stream itself is
             * available.
             * ---------------------------------------------------------------- */
            function openDocument() {
                showLoading();
                fetch(config.url, {
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/pdf' }
                }).then(function (response) {
                    if (!response.ok) throw new Error('The PDF request failed (HTTP ' + response.status + ').');
                    var contentType = response.headers.get('Content-Type') || '';
                    if (contentType && !/application\/(pdf|octet-stream)/i.test(contentType)) {
                        throw new Error('The server returned ' + contentType + ' instead of a PDF.');
                    }
                    return response.arrayBuffer();
                }).then(function (buffer) {
                    if (!buffer.byteLength) throw new Error('The PDF file is empty.');
                    return pdfjsLib.getDocument({ data: new Uint8Array(buffer) }).promise;
                }).then(function (pdf) {
                    state.pdf = pdf;
                    state.numPages = pdf.numPages;
                    return pdf.getPage(1).then(function (page) {
                        var viewport1 = page.getViewport({ scale: 1 });
                        state.aspect = viewport1.height / viewport1.width;
                        decideMode();
                        state.bucket = fitSize();
                        state.current = normalize(config.initialPage);
                        paintVisible();
                        updateControls();
                        preload();
                        buildToc(pdf);
                        var first = visiblePages().filter(function (p) { return p > 0; });
                        return ensure(first).then(function () {
                            paintVisible();
                            hideLoading();
                        });
                    });
                }).catch(function (error) {
                    hideLoading();
                    openNativeViewer((error && error.message ? error.message + ' — ' : '')
                        + 'opened with your browser’s built-in viewer.');
                });
            }

            /* ------------------------------------------------------------------
             * Wiring.
             * ---------------------------------------------------------------- */
            prevBtn.addEventListener('click', goPrev);
            nextBtn.addEventListener('click', goNext);
            zoomInBtn.addEventListener('click', function () { zoomBy(0.25); });
            zoomOutBtn.addEventListener('click', function () { zoomBy(-0.25); });
            zoomResetBtn.addEventListener('click', function () { applyZoom(1); });
            fullscreenBtn.addEventListener('click', toggleFullscreen);
            fullscreenTopBtn.addEventListener('click', toggleFullscreen);

            if (zoomSlider) {
                // Live label feedback while dragging, but only re-render the page on release.
                zoomSlider.addEventListener('input', function () {
                    state.zoom = parseFloat(zoomSlider.value) || 1;
                    zoomResetBtn.textContent = Math.round(state.zoom * 100) + '%';
                });
                zoomSlider.addEventListener('change', function () {
                    applyZoom(parseFloat(zoomSlider.value) || 1);
                });
            }

            if (tocBtn) {
                tocBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (tocEl.hidden) openToc(); else closeToc();
                });
            }
            if (tocClose) tocClose.addEventListener('click', closeToc);

            if (settingsBtn) {
                settingsBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (settingsEl.hidden) openSettings(); else closeSettings();
                });
            }
            if (jumpGo) jumpGo.addEventListener('click', jumpToPage);
            if (jumpInput) {
                jumpInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); jumpToPage(); }
                });
            }

            Array.prototype.forEach.call(viewInputs, function (input) {
                input.checked = input.value === state.pref;
                input.addEventListener('change', function () {
                    state.pref = input.value;
                    try { window.localStorage.setItem('bkr.view', state.pref); } catch (prefErr) { /* ignore */ }
                    relayout();
                });
            });

            document.addEventListener('click', function (e) {
                if (settingsEl && !settingsEl.hidden && !settingsEl.contains(e.target) && e.target !== settingsBtn) {
                    closeSettings();
                }
                if (tocEl && !tocEl.hidden && !tocEl.contains(e.target) && e.target !== tocBtn) {
                    closeToc();
                }
            });

            document.addEventListener('fullscreenchange', function () {
                root.classList.toggle('reader--immersive', isFullscreen());
                syncFullscreenUi();
                window.requestAnimationFrame(function () {
                    if (state.pdf && !state.rendering) renderPage(state.current);
                });
            });

            var resizeTimer = null;
            window.addEventListener('resize', function () {
                if (resizeTimer) window.clearTimeout(resizeTimer);
                resizeTimer = window.setTimeout(function () {
                    if (state.pdf && !state.rendering) renderPage(state.current);
                }, 150);
            });

            window.addEventListener('pagehide', function () {
                if (saveTimer) {
                    window.clearTimeout(saveTimer);
                    saveTimer = null;
                    var page = progressPage();
                    if (page) saveProgress(page);
                }
            });

            /* Tap zones and swipe: left/right thirds turn pages in single-page
               mode, halves do it on a spread; a horizontal swipe wins over a
               tap and suppresses the click that follows it. */
            var swipeFrom = null;
            var swiped = false;

            scrollEl.addEventListener('pointerdown', function (e) {
                swipeFrom = { x: e.clientX, y: e.clientY };
                swiped = false;
            });

            scrollEl.addEventListener('pointerup', function (e) {
                if (!swipeFrom) return;
                var dx = e.clientX - swipeFrom.x;
                var dy = e.clientY - swipeFrom.y;
                swipeFrom = null;
                if (Math.abs(dx) > 60 && Math.abs(dy) < 50) {
                    swiped = true;
                    turn(dx < 0 ? 'next' : 'prev');
                }
            });

            scrollEl.addEventListener('click', function (e) {
                if (swiped) { swiped = false; return; }
                if (e.target.closest('button, a, input, label')) return;
                var bounds = scrollEl.getBoundingClientRect();
                var x = e.clientX - bounds.left;
                if (state.mode === 'two') {
                    turn(x < bounds.width / 2 ? 'prev' : 'next');
                } else {
                    var zone = bounds.width * 0.3;
                    if (x < zone) turn('prev');
                    else if (x > bounds.width - zone) turn('next');
                }
            });

            document.addEventListener('keydown', function (e) {
                var tag = (e.target && e.target.tagName) || '';
                if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;

                if (e.key === 'Escape' && settingsEl && !settingsEl.hidden) { closeSettings(); return; }
                if (e.key === 'Escape' && tocEl && !tocEl.hidden) { closeToc(); return; }

                if (e.key === 'ArrowLeft') { e.preventDefault(); goPrev(); }
                else if (e.key === 'ArrowRight') { e.preventDefault(); goNext(); }
                else if (e.key === '+' || e.key === '=') { e.preventDefault(); zoomBy(0.25); }
                else if (e.key === '-' || e.key === '_') { e.preventDefault(); zoomBy(-0.25); }
                else if (e.key === 'Escape' && !isFullscreen() && root.classList.contains('reader--immersive')) {
                    root.classList.remove('reader--immersive');
                    syncFullscreenUi();
                }
            });

            openDocument();
        })();
    </script>
@endpush

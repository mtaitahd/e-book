@extends('layouts.customer.app')

@section('title', 'Reading ' . $purchase->book->title)

@section('body_class', 'page-online-reader')

{{-- A dedicated reading app: no storefront chrome around it. --}}
@section('hide_site_chrome', true)

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/online-reader.css') }}">
@endpush

@section('content')
    @php
        $book = $purchase->book;
    @endphp

    <div class="orp" id="online-reader" data-theme="book">
        {{-- Floating premium toolbar: library · contents/bookmarks | page · title · page | tools. --}}
        <header class="orp-head">
            <div class="orp-head__side orp-head__side--start">
                <a class="orp-btn orp-back" href="{{ route('account.purchases.index') }}" title="Back to My Library">
                    <span class="orp-ico" aria-hidden="true">&larr;</span>
                    <span class="orp-lbl">Library</span>
                </a>

                <span class="orp-divider" aria-hidden="true"></span>

                <button type="button" class="orp-btn" id="orp-toc-toggle" aria-expanded="false" aria-controls="orp-toc" title="Table of contents">
                    <span class="orp-ico" aria-hidden="true">&#9776;</span>
                    <span class="orp-lbl">Contents</span>
                </button>

                <button type="button" class="orp-btn" id="orp-mark" aria-expanded="false" aria-controls="orp-marks" title="Bookmarks">
                    <span class="orp-ico" aria-hidden="true">&#9734;</span>
                    <span class="orp-lbl">Bookmarks</span>
                </button>
            </div>

            <div class="orp-head__mid">
                <button type="button" class="orp-btn orp-tool-turn" id="orp-tool-prev" aria-label="Previous page" title="Previous page">
                    <span class="orp-ico" aria-hidden="true">&lsaquo;</span>
                </button>

                <span class="orp-head__titleblock">
                    <span class="orp-head__title" id="orp-book-title">{{ $book->title }}</span>
                    <span class="orp-head__chapter" id="orp-chapter-label"></span>
                </span>

                <button type="button" class="orp-btn orp-tool-turn" id="orp-tool-next" aria-label="Next page" title="Next page">
                    <span class="orp-ico" aria-hidden="true">&rsaquo;</span>
                </button>
            </div>

            <div class="orp-head__side orp-head__side--end">
                <button type="button" class="orp-btn" id="orp-theme" aria-label="Change colour theme" title="Change colour theme">Book</button>

                <span class="orp-divider" aria-hidden="true"></span>

                <span class="orp-group" role="group" aria-label="Text size">
                    <button type="button" class="orp-btn" id="orp-font-out" aria-label="Decrease text size" title="Smaller text">A&minus;</button>
                    <span class="orp-metric" id="orp-font-label" aria-live="polite">100%</span>
                    <button type="button" class="orp-btn" id="orp-font-in" aria-label="Increase text size" title="Larger text">A+</button>
                </span>

                <span class="orp-divider" aria-hidden="true"></span>

                <button type="button" class="orp-btn" id="orp-fullscreen" aria-label="Toggle fullscreen" title="Fullscreen">
                    <span class="orp-ico" aria-hidden="true">&#10530;</span>
                </button>

                <a class="orp-btn" id="orp-pdf-link" title="Open the PDF edition"
                   href="{{ route('account.purchases.read-pdf', $purchase) }}"
                   @if (! $book->hasPdfFile()) hidden @endif
                >PDF</a>
            </div>
        </header>

        <div class="orp-body">
            {{-- Large circular page-turn arrows, flanking the open book. --}}
            <button type="button" class="orp-arrow orp-arrow--prev" id="orp-prev" aria-label="Previous page" title="Previous page">
                <span class="orp-ico" aria-hidden="true">&lsaquo;</span>
            </button>
            <button type="button" class="orp-arrow orp-arrow--next" id="orp-next" aria-label="Next page" title="Next page">
                <span class="orp-ico" aria-hidden="true">&rsaquo;</span>
            </button>

            <aside class="orp-panel" id="orp-toc" aria-label="Table of contents" hidden>
                <h2>Contents</h2>
                <ol class="orp-list" id="orp-toc-list"></ol>
                <p class="orp-hint">Keys: &larr;/&rarr; page, T contents, B bookmark, F fullscreen, +/&minus; text size.</p>
            </aside>

            <div class="orp-stage" id="orp-stage" tabindex="0" aria-busy="true">
                <div class="orp-pages" id="orp-pages">
                    <div class="orp-cover" id="orp-cover" hidden></div>
                </div>
                <p class="orp-status" id="orp-status" role="status" aria-live="polite">Loading your e-book&hellip;</p>
            </div>

            <aside class="orp-panel" id="orp-marks" aria-label="Bookmarks" hidden>
                <h2>Bookmarks</h2>
                <button type="button" class="orp-btn" id="orp-mark-add">Bookmark this page</button>
                <ul class="orp-list" id="orp-mark-list"></ul>
            </aside>
        </div>

        {{-- Floating bottom bar: chapters flanking the reading-progress track. --}}
        <footer class="orp-foot">
            <button type="button" class="orp-btn orp-btn--chapter" id="orp-prev-chapter" aria-label="Previous chapter" title="Previous chapter">
                <span class="orp-ico" aria-hidden="true">&laquo;</span>
                <span class="orp-lbl">Chapter</span>
            </button>

            <div class="orp-foot__track">
                <svg class="orp-foot__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                    <path d="M12 5.2C10.4 3.9 7.8 3.6 4 3.8v14.4c3.8-.2 6.4.1 8 1.4 1.6-1.3 4.2-1.6 8-1.4V3.8c-3.8-.2-6.4.1-8 1.4Z"
                          fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M12 5.2v14.4" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                </svg>
                <span class="orp-pageinfo">
                    Page <span id="orp-page-now">1</span> / <span id="orp-page-total">1</span>
                </span>
                <span class="orp-progress" aria-label="Reading progress">
                    <span class="orp-progress__bar" id="orp-progress-bar"></span>
                    <span class="orp-progress__pin" id="orp-progress-pin"></span>
                </span>
                <span class="orp-percent"><span id="orp-percent">0</span>%</span>
                <span class="orp-save" id="orp-save-state" role="status" aria-live="polite"></span>
            </div>

            <button type="button" class="orp-btn orp-btn--chapter" id="orp-next-chapter" aria-label="Next chapter" title="Next chapter">
                <span class="orp-lbl">Chapter</span>
                <span class="orp-ico" aria-hidden="true">&raquo;</span>
            </button>
        </footer>
    </div>

    {{-- Boot payload only. Chapter HTML is fetched through an authorized
         endpoint and sanitized again on the server before it is returned.
         Kept in a plain variable so Blade's @json() has no nested calls to
         mis-parse. --}}
    @php
        $saved = $purchase->readingProgress;

        $config = [
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'format' => $book->format(),
                'has_pdf' => $book->hasPdfFile(),
                'cover' => $book->cover_image ? asset('storage/' . $book->cover_image) : null,
                'author' => optional($book->authors->first())->name,
            ],
            'chapters' => $book->chapters->map(fn ($chapter) => [
                'id' => $chapter->id,
                'title' => $chapter->title,
                'position' => (int) $chapter->position,
                'is_free' => (bool) $chapter->is_free,
            ])->values(),
            'progress' => $saved === null ? null : [
                'chapter_id' => $saved->chapter_id,
                'page' => (int) $saved->current_page,
                'percent' => (float) $saved->progress_percent,
            ],
            'bookmarks' => $purchase->bookmarks->map(fn ($bookmark) => [
                'id' => (int) $bookmark->id,
                'chapter_id' => $bookmark->chapter_id === null ? null : (int) $bookmark->chapter_id,
                'page' => (int) $bookmark->page,
                'label' => $bookmark->label,
            ])->values(),
            'urls' => [
                'chapter' => route('account.purchases.online.chapter', [
                    'purchase' => $purchase,
                    'chapter' => '__CHAPTER__',
                ]),
                'progress' => route('account.purchases.online.progress', $purchase),
                'bookmark' => route('account.purchases.online.bookmarks.store', $purchase),
            ],
        ];
    @endphp
    <script type="application/json" id="online-reader-config">@json($config)</script>
@endsection

@push('scripts')
    <script src="{{ asset('assets/storefront/js/online-reader.js') }}"></script>
@endpush

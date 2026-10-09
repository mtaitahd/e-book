<div class="ebs-filterbar" role="navigation" aria-label="Catalogue filters">
    <span class="ebs-filterbar__label">Filter by</span>
    <a class="pill-filter{{ !request('format') ? ' is-active' : '' }}" href="{{ route('books.index') }}">All Books</a>
    <a class="pill-filter" href="{{ route('home') }}#latest">Latest Books</a>
    {{-- The home page no longer renders a category strip, so there is no #categories
         anchor to jump to. Categories are browsed from the catalogue via the header
         dropdown and the footer, so this points at the real catalogue page. --}}
    <a class="pill-filter" href="{{ route('books.index') }}">Categories</a>
    <a class="pill-filter{{ request('format') === 'pdf' ? ' is-active' : '' }}" href="{{ route('books.index', ['format' => 'pdf']) }}">PDF Books</a>
    @auth
        <a class="pill-filter" href="{{ route('account.purchases.index') }}">Your Books</a>
    @endauth
    <button type="button" class="ebs-filterbar__icon" aria-label="Filter options">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 6h7m4 0h7M3 12h4m6 0h8M3 18h10m4 0h4"/>
            <circle cx="14" cy="6" r="2"/><circle cx="9" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>
        </svg>
    </button>
</div>
@extends('layouts.storefront.app')

@section('title', request('q') ? 'Search results: ' . request('q') : 'Books')

@section('content')
    @include('partials.filter-bar')

    {{-- The "All Books" hero was removed. A search still reports how much it
         found, as a plain line rather than a banner. --}}
    @if(request('q'))
        <p class="muted books-results-line">
            {{ $books->total() }} result{{ $books->total() === 1 ? '' : 's' }} for &ldquo;{{ request('q') }}&rdquo;
        </p>
    @endif

    <div class="book-grid">
        @forelse($books as $book)
            @include('partials.book-card', ['book' => $book, 'showCategories' => true])
        @empty
            <div class="muted-box">
                {!! request('q')
                    ? 'Nothing matched your search. Try a different title, author or category.'
                    : 'No published books yet.' !!}
            </div>
        @endforelse
    </div>

    {{ $books->links() }}
@endsection
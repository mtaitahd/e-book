@extends('layouts.storefront.app')

@section('title', 'E-Book')

@section('content')
    @include('partials.filter-bar')

    <section class="section" id="latest">
        <div class="section__head">
            <h2>Latest Published Books</h2>
            <a class="section__more" href="{{ route('books.index') }}">See all &rarr;</a>
        </div>

        <div class="book-grid">
            @forelse($books as $book)
                @include('partials.book-card', ['book' => $book, 'showCategories' => true])
            @empty
                <div class="muted-box">No published books yet.</div>
            @endforelse
        </div>
    </section>

    @if($moreBooks->isNotEmpty())
        <section class="section" id="more">
            <div class="section__head">
                <h2>More Books</h2>
                <a class="section__more" href="{{ route('books.index') }}">See all &rarr;</a>
            </div>
            <div class="book-grid">
                @foreach($moreBooks as $book)
                    @include('partials.book-card', ['book' => $book, 'showCategories' => true])
                @endforeach
            </div>
        </section>
    @endif
@endsection

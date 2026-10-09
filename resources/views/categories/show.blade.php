@extends('layouts.storefront.app')

@section('title', $category->name)

@section('content')
    <p class="back-link"><a href="{{ route('books.index') }}">&larr; All books</a></p>

    <div class="page-head">
        <h1>{{ $category->name }}</h1>
        @if($category->description)
            <p class="lead">{{ $category->description }}</p>
        @endif
    </div>

    <div class="book-grid">
        @forelse($category->books as $book)
            @include('partials.book-card', ['book' => $book, 'showCategories' => false])
        @empty
            <div class="muted-box">No published books in this category.</div>
        @endforelse
    </div>
@endsection
@extends('layouts.storefront.app')

@section('title', 'Free preview: ' . $chapter->title . ' — ' . $book->title)

@section('content')
    <div class="container py-4">
        <nav aria-label="Breadcrumb" class="mb-3">
            <a class="ebs-link small" href="{{ route('books.show', $book) }}">&larr; Back to {{ $book->title }}</a>
        </nav>

        <div class="card">
            <div class="card-body p-4">
                <p class="text-uppercase small fw-bold text-muted mb-1">Free preview</p>
                <h1 class="h3 mb-1">{{ $chapter->title }}</h1>
                <p class="text-muted small mb-4">
                    {{ $book->title }}
                    @if ($book->authors->isNotEmpty())
                        · {{ $book->authors->pluck('name')->join(', ') }}
                    @endif
                </p>

                <div class="orp-content orp-public-preview">{!! $html !!}</div>

                <hr class="my-4">

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <p class="mb-0 small text-muted">
                        Enjoying this sample? The full e-book is available to buy.
                    </p>

                    <div class="d-flex gap-2">
                        <a class="btn btn-outline-secondary" href="{{ route('books.show', $book) }}">Book details</a>
                        <form method="POST" action="{{ route('cart.add') }}">
                            @csrf
                            <input type="hidden" name="book_id" value="{{ $book->id }}">
                            <button type="submit" class="btn btn-warning fw-bold">Buy this e-book</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/online-reader.css') }}">
    <style>
        .orp-public-preview {
            max-width: 42rem;
            font-size: 1.0625rem;
            line-height: 1.75;
        }
    </style>
@endpush

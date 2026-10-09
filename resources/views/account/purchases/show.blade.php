@extends('layouts.customer.app')

@section('title', $purchase->book->title)

@section('content')
    <p class="back-link"><a href="{{ route('account.purchases.index') }}">&larr; Back to my purchases</a></p>
    @include('partials.customer-nav')

    <div class="detail-layout">
        <div class="detail-gallery">
            @if($purchase->book->cover_image)
                <img src="{{ asset('storage/' . $purchase->book->cover_image) }}" alt="{{ $purchase->book->title }}">
            @else
                <div class="detail-gallery__none">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                    </svg>
                    <span>E-Book</span>
                </div>
            @endif
        </div>

        <div class="detail-main">
            <h1>{{ $purchase->book->title }}</h1>
            @if($purchase->book->authors->isNotEmpty())
                <p class="detail-meta">by {{ $purchase->book->authors->pluck('name')->join(', ') }}</p>
            @endif

            <hr class="rule">

            <h2>About this book</h2>
            <div class="attr">
                <div class="k">Purchased</div>
                <div>{{ $purchase->purchased_at?->format('M j, Y') ?? $purchase->created_at->format('M j, Y') }}</div>
            </div>

            <div class="attr">
                <div class="k">Format</div>
                <div>{{ $purchase->book->formatLabel() }}</div>
            </div>

            @if($purchase->order)
                <div class="attr">
                    <div class="k">Order</div>
                    <div><a href="{{ route('account.orders.show', $purchase->order) }}">{{ $purchase->order->order_number }}</a></div>
                </div>
            @endif

            <h2>Description</h2>
            <p class="lead">{{ $purchase->book->description ?: 'No description available.' }}</p>
        </div>

        <aside class="buybox">
            @php
                $book = $purchase->book;
                $resume = $purchase->readingProgress;
            @endphp

            <p class="buybox__price">{{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($purchase->amount), $purchase->currency) }}</p>
            <p class="buybox__owned">&#10003; You own this book</p>
            <p class="buybox__format">Format: <b>{{ $book->formatLabel() }}</b></p>

            @if($resume)
                <p class="buybox__note">
                    {{ $resume->progress_percent > 0 ? 'You are ' . round($resume->progress_percent) . '% through' : 'You have started reading' }} this book.
                </p>
            @endif

            <div class="buybox__actions">
                @if($book->hasOnlineReading() || $book->hasPdfFile())
                    <a class="btn btn--primary btn--block" href="{{ route('account.purchases.read', $purchase) }}">
                        {{ $book->hasOnlineReading() ? 'Read online' : 'Read now' }}
                    </a>
                @else
                    <span class="btn btn--disabled btn--block" title="No file uploaded yet">Read now</span>
                @endif

                @if($book->hasPdfFile())
                    <a class="btn btn--secondary btn--block" href="{{ route('account.purchases.download', $purchase) }}">Download now</a>
                @elseif(! $book->hasOnlineReading())
                    <span class="btn btn--disabled btn--block" title="No file uploaded yet">Download</span>
                @endif
            </div>

            @if(! $book->hasOnlineReading() && ! $book->hasPdfFile())
                <p class="buybox__note">This book is not available for reading yet. Please contact support.</p>
            @endif
            <p class="buybox__note">Digital e-book with lifetime access.</p>
        </aside>
    </div>
@endsection
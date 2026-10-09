@extends('layouts.customer.app')

@section('title', 'My Purchases')

@section('content')
    <h1>My Purchases</h1>
    <p class="lead">Books you own. Download them whenever you like, as many times as you like.</p>
    @include('partials.customer-nav')

    @if($purchases->isEmpty())
        <div class="muted-box">
            <h4>No purchases yet</h4>
            <p>Once you complete a payment, the books you bought will appear here for download.</p>
            <p><a href="{{ route('books.index') }}">Browse books</a></p>
        </div>
    @else
        <div class="library-list">
            @foreach($purchases as $purchase)
                <div class="library-card">
                    @if($purchase->book->cover_image)
                        <img src="{{ asset('storage/' . $purchase->book->cover_image) }}" alt="{{ $purchase->book->title }}">
                    @else
                        <span class="library-card__none">E-Book</span>
                    @endif
                    <div class="library-card__body">
                        @php
                            $book = $purchase->book;
                            $readable = $book->hasOnlineReading() || $book->hasPdfFile();
                        @endphp
                        <a class="library-card__title" href="{{ route('account.purchases.show', $purchase) }}">{{ $book->title }}</a>
                        @if($book->authors->isNotEmpty())
                            <span class="library-card__meta">by {{ $book->authors->pluck('name')->join(', ') }}</span>
                        @endif
                        <span class="library-card__meta">Purchased {{ $purchase->purchased_at?->format('M j, Y') ?? $purchase->created_at->format('M j, Y') }}</span>
                        <div class="purchase-actions">
                            @if($readable)
                                <a class="btn btn--primary btn--sm" href="{{ route('account.purchases.read', $purchase) }}">
                                    {{ $book->hasOnlineReading() ? 'Read online' : 'Read Now' }}
                                </a>
                            @else
                                <span class="btn-disabled" title="This book is not available for reading yet">Read Now</span>
                            @endif
                            <a class="btn btn--secondary btn--sm" href="{{ route('account.purchases.show', $purchase) }}">View Book</a>
                            @if($book->hasPdfFile())
                                <a class="btn btn--secondary btn--sm" href="{{ route('account.purchases.download', $purchase) }}">Download</a>
                            @elseif($book->hasOnlineReading())
                                <span class="library-card__meta">{{ $book->formatLabel() }}</span>
                            @else
                                <span class="btn-disabled" title="No file uploaded yet">Download</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
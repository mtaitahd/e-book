@extends('layouts.storefront.app')

@section('title', $book->title)

@section('content')
    <p class="back-link"><a href="{{ route('books.index') }}">&larr; All books</a></p>

    <div class="detail-layout">
        <div class="detail-gallery">
            @include('partials.book3d', ['book' => $book, 'variant' => 'detail'])
        </div>

        <div class="detail-main">
            <h1>{{ $book->title }}</h1>

            @if($book->authors->isNotEmpty())
                <p class="detail-meta">by {{ $book->authors->pluck('name')->join(', ') }}</p>
            @endif

            {{-- One badge row: what this book IS (E-Book or PDF) first, then
                 how it is organised (the category links). The full format
                 label stays in the edition details below. --}}
            <p class="detail-pills">
                <span class="pill">{{ $book->typeLabel() }}</span>
                @foreach($book->categories as $category)
                    <a class="pill" href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>
                @endforeach
            </p>

            <hr class="rule">

            <h2>Description</h2>
            <p class="lead">{{ $book->description ?: 'No description available.' }}</p>

            <h2>About this edition</h2>
            <div class="attr">
                <div class="k">Format</div>
                <div>{{ $book->formatLabel() }}</div>
            </div>

            <div class="attr">
                <div class="k">Price</div>
                <div>
                    @if($book->isFree())
                        Free
                    @else
                        {{ \App\Support\Money::format($book->price) }}
                    @endif
                </div>
            </div>

            @if($book->publisher)
                <div class="attr">
                    <div class="k">Publisher</div>
                    <div>{{ $book->publisher }}</div>
                </div>
            @endif

            @if($book->published_at)
                <div class="attr">
                    <div class="k">Published</div>
                    <div>{{ $book->published_at->format('M j, Y') }}</div>
                </div>
            @endif
        </div>

        <aside class="buybox">
            <p class="buybox__price @if($book->isFree()) buybox__price--free @endif">
                @if($book->isFree())
                    Free
                @else
                    {{ \App\Support\Money::format($book->price) }}
                @endif
            </p>
            <p class="buybox__format">
                Format: <b>{{ $book->formatLabel() }}</b>
            </p>

            @php
                $freeChapter = $book->freeChapter();
            @endphp

            @auth
                @php
                    $owned = auth()->user()->purchases()
                        ->with('order')
                        ->where('book_id', $book->id)
                        ->get()
                        ->first(fn ($purchase) => $purchase->order?->isPaid());
                @endphp
                @if($owned)
                    <p class="buybox__owned">&#10003; You own this book</p>
                    <div class="buybox__actions">
                        {{-- The read route picks the native reader or PDF.js
                             based on the book's format, so one link is enough. --}}
                        @if($book->hasOnlineReading() || $book->hasPdfFile())
                            <a class="btn btn--primary btn--block" href="{{ route('account.purchases.read', $owned) }}">
                                {{ $book->hasOnlineReading() ? 'Read online' : 'Read now' }}
                            </a>
                        @endif

                        @if($book->hasPdfFile())
                            <a class="btn btn--secondary btn--block" href="{{ route('account.purchases.download', $owned) }}">Download</a>
                        @elseif(! $book->hasOnlineReading())
                            <span class="btn btn--disabled btn--block" title="No file uploaded yet">Download</span>
                        @endif
                    </div>
                @elseif($book->isFree())
                    {{-- A free book is claimed straight into the library: there
                         is no cart, no order and no payment to make. --}}
                    <form method="POST" action="{{ route('books.claim-free', $book) }}">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--block">Add to my library</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('cart.add') }}">
                        @csrf
                        <input type="hidden" name="book_id" value="{{ $book->id }}">
                        <button type="submit" class="btn btn--primary btn--block">Add to cart</button>
                    </form>
                @endif
            @endauth

            @guest
                @if($book->isFree())
                    <p class="buybox__owned" style="opacity:.55">
                        <a href="{{ route('login') }}" data-ebs-auth-open="login">Sign in</a> to add this free book to your library.
                    </p>
                @else
                    <form method="POST" action="{{ route('cart.add') }}">
                        @csrf
                        <input type="hidden" name="book_id" value="{{ $book->id }}">
                        <button type="submit" class="btn btn--primary btn--block">Add to cart</button>
                    </form>
                    <p class="buybox__note"><a href="{{ route('login') }}" data-ebs-auth-open="login">Sign in</a> to read and download your purchased books.</p>
                @endif
            @endguest

            @if($freeChapter)
                <p class="buybox__note">
                    <a class="ebs-link" href="{{ route('books.preview', [$book, $freeChapter]) }}">
                        Read a free sample
                    </a>
                </p>
            @endif

            <p class="buybox__note">
                @if($book->isFree())
                    Free to keep. Added to your library straight away, with no payment.
                @else
                    Digital e-book. Lifetime access from your library after purchase.
                @endif
            </p>
        </aside>
    </div>
@endsection
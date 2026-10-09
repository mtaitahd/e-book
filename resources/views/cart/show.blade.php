@extends('layouts.storefront.app')

@section('title', 'Your Cart')

@section('content')
    <div class="page-head">
        <h1>Shopping Cart</h1>
    </div>

    @if($lines->isEmpty())
        <div class="empty-cart">
            <div class="empty-cart__icon">
                <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/>
                    <path d="M2.5 3h2l2.6 12.4a1.5 1.5 0 0 0 1.5 1.1h10.4a1.5 1.5 0 0 0 1.5-1.2L22 7H6"/>
                </svg>
            </div>
            <h2>Your cart is empty</h2>
            <p><a href="{{ route('books.index') }}">Browse books</a> to add something to your cart.</p>
        </div>
    @else
        <div class="cart-layout">
            <div class="cart-lines">
                @foreach($lines as $line)
                    <div class="cart-line">
                        @if($line['book']->cover_image)
                            <img src="{{ asset('storage/' . $line['book']->cover_image) }}" alt="{{ $line['book']->title }}">
                        @else
                            <span class="cart-line__none">E-Book</span>
                        @endif
                        <div class="cart-line__info">
                            <a class="cart-line__title" href="{{ route('books.show', $line['book']) }}">{{ $line['book']->title }}</a>
                            @if($line['book']->authors->isNotEmpty())
                                <div class="cart-line__meta">by {{ $line['book']->authors->pluck('name')->join(', ') }}</div>
                            @endif
                            <form method="POST" action="{{ route('cart.remove') }}" class="cart-line__remove">
                                @csrf
                                <input type="hidden" name="book_id" value="{{ $line['book']->id }}">
                                <button type="submit" class="btn--link">Remove</button>
                            </form>
                        </div>
                        <div class="cart-line__price">{{ \App\Support\Money::format($line['unit_price']) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="summary-card">
                <h2>Cart summary</h2>
                <div class="summary-row">
                    <span>{{ $count }} book{{ $count === 1 ? '' : 's' }}</span>
                    <span>{{ \App\Support\Money::format($total) }}</span>
                </div>
                <div class="summary-row summary-row--total">
                    <span>Total</span>
                    <span>{{ \App\Support\Money::format($total) }}</span>
                </div>
                @guest
                    {{-- /checkout requires a session, so guests get the auth modal
                         instead of a redirect to the administrator login page. The
                         href stays as the no-JS fallback. --}}
                    <a class="btn btn--primary btn--block" style="margin-top:14px;"
                        href="{{ route('checkout.show') }}"
                        data-ebs-checkout
                        data-ebs-return="{{ route('checkout.show') }}">Proceed to checkout &rarr;</a>
                @else
                    <a class="btn btn--primary btn--block" style="margin-top:14px;" href="{{ route('checkout.show') }}">Proceed to checkout &rarr;</a>
                @endguest
                <form method="POST" action="{{ route('cart.clear') }}" style="margin-top:10px;">
                    @csrf
                    <button type="submit" class="btn--link" style="width:100%;justify-content:center;">Empty cart</button>
                </form>
                <p class="summary-note">Digital e-books are delivered to your library instantly after purchase.</p>
            </div>
        </div>
    @endif
@endsection

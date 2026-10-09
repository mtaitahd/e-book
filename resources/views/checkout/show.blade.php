@extends('layouts.storefront.app')

@section('title', 'Checkout')

@section('content')
    <h1>Checkout</h1>
    <p class="lead">Review your order below. Payment is handled in a later stage; placing the order confirms your purchase intent.</p>

    <div class="checkout-layout">
        <div class="checkout-card">
            <h2>Items in your order</h2>
            <div class="checkout-note">Order contents and price confirmation.</div>
            @foreach($lines as $line)
                <div class="checkout-line">
                    @if($line['book']->cover_image)
                        <img src="{{ asset('storage/' . $line['book']->cover_image) }}" alt="{{ $line['book']->title }}">
                    @else
                        <span class="cart-line__none" style="width:44px;height:60px;">E</span>
                    @endif
                    <div>
                        <a class="cart-line__title" href="{{ route('books.show', $line['book']) }}">{{ $line['book']->title }}</a>
                        <div class="cart-line__meta">Digital e-book</div>
                    </div>
                    <span class="checkout-line__price">{{ \App\Support\Money::format($line['unit_price']) }}</span>
                </div>
            @endforeach
        </div>

        <div class="summary-card">
            <h2>Order summary</h2>
            <div class="summary-row">
                <span>Items ({{ $count }})</span>
                <span>{{ \App\Support\Money::format($total) }}</span>
            </div>
            <div class="summary-row summary-row--total">
                <span>Total</span>
                <span>{{ \App\Support\Money::format($total) }}</span>
            </div>
            <form method="POST" action="{{ route('checkout.store') }}" style="margin-top:14px;">
                @csrf
                <button type="submit" class="btn btn--primary btn--block btn--lg">Place order</button>
            </form>
            <p class="summary-note"><a href="{{ route('cart.show') }}">&larr; Back to cart</a></p>
        </div>
    </div>
@endsection
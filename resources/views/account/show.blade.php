@extends('layouts.customer.app')

@section('title', 'My Account')

@section('content')
    @include('partials.customer-nav')

    <h1>My Account</h1>
    <p class="lead">Welcome back, {{ auth()->user()->name }}.</p>

    <div class="card-grid">
        <div class="account-card">
            <h3>Profile</h3>
            <table class="profile" style="width:100%;border:none;background:none;">
                <tr><th>Name</th><td>{{ auth()->user()->name }}</td></tr>
                <tr><th>Email</th><td>{{ auth()->user()->email }}</td></tr>
                <tr>
                    <th>Role</th>
                    <td><span class="badge {{ auth()->user()->isAdmin() ? 'admin' : 'customer' }}">{{ ucfirst(auth()->user()->role) }}</span></td>
                </tr>
                <tr>
                    <th>Status</th>
                    <td><span class="badge {{ auth()->user()->isActive() ? 'active' : 'admin' }}">{{ ucfirst(auth()->user()->status) }}</span></td>
                </tr>
            </table>
        </div>

        <div class="account-card">
            <h3>Your library</h3>
            <p>Books you own, ready to read online or download any time.</p>
            @if($recentPurchases->isNotEmpty())
                <ul style="list-style:none;margin-bottom:10px;">
                    @foreach($recentPurchases as $purchase)
                        <li style="padding:3px 0;">
                            <a href="{{ route('account.purchases.show', $purchase) }}">{{ $purchase->book->title }}</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted">No purchases yet. Once you complete a payment, your books appear here.</p>
            @endif
            <a class="btn btn--secondary btn--sm" href="{{ route('account.purchases.index') }}">My Books</a>
        </div>

        <div class="account-card">
            <h3>Orders</h3>
            <p>Track the status of your recent orders.</p>
            @if($recentOrders->isNotEmpty())
                <ul style="list-style:none;margin-bottom:10px;">
                    @foreach($recentOrders as $order)
                        <li style="padding:3px 0;">
                            <a href="{{ route('account.orders.show', $order) }}">{{ $order->order_number }}</a>
                            <span class="badge {{ $order->status }}" style="margin-left:6px;">{{ ucfirst($order->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="muted">No orders yet. When you place an order it will be listed here.</p>
            @endif
            <a class="btn btn--secondary btn--sm" href="{{ route('account.orders.index') }}">View orders</a>
        </div>
    </div>
@endsection
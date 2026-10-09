@extends('layouts.customer.app')

@section('title', 'My Orders')

@section('content')
    <h1>My Orders</h1>
    <p class="lead"><a href="{{ route('account.show') }}">&larr; Back to account</a></p>
    @include('partials.customer-nav')

    @if($orders->isEmpty())
        <div class="muted-box">
            <h4>No orders yet</h4>
            <p>When you place an order it will appear here with its status.</p>
            <p><a href="{{ route('books.index') }}">Browse books</a></p>
        </div>
    @else
        <div class="table-scroll">
            <table class="simple-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Placed</th>
                        <th class="right">Total</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->created_at->format('M j, Y g:i A') }}</td>
                            <td class="right">{{ \App\Support\Money::format($order->total, $order->currency) }}</td>
                            <td>
                                <span class="badge {{ $order->status }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="right">
                                @if($order->isPaid())
                                    <a class="btn-outline" href="{{ route('account.purchases.index') }}">My Books</a>
                                @endif
                                <a class="btn-outline" href="{{ route('account.orders.show', $order) }}">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pagination-wrap">
            {{ $orders->links() }}
        </div>
    @endif
@endsection
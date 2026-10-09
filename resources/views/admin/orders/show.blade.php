@extends('layouts.admin.app')

@section('title', 'Order ' . $order->order_number)
@section('heading', 'Order ' . $order->order_number)

@section('content')
    <div class="container-fluid">
        <div class="mb-3">
            <a href="{{ route('admin.orders.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> All orders
            </a>
        </div>

        <div class="row">
            <div class="col-xl-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Order details</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th class="w-50">Order number</th><td>{{ $order->order_number }}</td></tr>
                            <tr><th>Placed</th><td>{{ $order->created_at->format('M j, Y g:i A') }}</td></tr>
                            <tr><th>Status</th><td><span class="badge badge-{{ $order->statusBadge() }}">{{ ucfirst($order->status) }}</span></td></tr>
                            <tr><th>Currency</th><td>{{ $order->currency }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Customer</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th class="w-50">Name</th><td>{{ $order->user->name }}</td></tr>
                            <tr><th>Email</th><td>{{ $order->user->email }}</td></tr>
                            <tr><th>Role</th><td>{{ ucfirst($order->user->role) }}</td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Items</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Book</th>
                                <th scope="col" style="width: 80px;" class="text-right">Qty</th>
                                <th scope="col" style="width: 140px;" class="text-right">Unit price</th>
                                <th scope="col" style="width: 140px;" class="text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->items as $item)
                                <tr>
                                    <td>
                                        <a href="{{ route('books.show', $item->book) }}">{{ $item->book->title }}</a>
                                    </td>
                                    <td class="text-right">{{ $item->quantity }}</td>
                                    <td class="text-right">{{ \App\Support\Money::format($item->unit_price, $order->currency) }}</td>
                                    <td class="text-right">{{ \App\Support\Money::format($item->subtotal, $order->currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="thead-light">
                            <tr>
                                <th colspan="3" class="text-right">Total</th>
                                <th class="text-right">{{ \App\Support\Money::format($order->total, $order->currency) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Payments</h6>
            </div>
            <div class="card-body p-0">
                @forelse($order->payments as $payment)
                    <table class="table table-sm mb-0">
                        <tr><th class="w-50">Status</th><td><span class="badge badge-{{ $payment->isCompleted() ? 'success' : ($payment->isPending() ? 'warning' : 'secondary') }}">{{ ucfirst($payment->status) }}</span></td></tr>
                        <tr><th>Amount</th><td>{{ \App\Support\Money::formatWhole($payment->amount, $payment->currency) }}</td></tr>
                        <tr><th>Network</th><td>{{ $payment->channel_provider ? \Illuminate\Support\Str::title(str_replace('_', ' ', $payment->channel_provider)) : '—' }}</td></tr>
                        <tr><th>Provider reference</th><td>{{ $payment->provider_reference ?? '—' }}</td></tr>
                        <tr><th>External reference</th><td>{{ $payment->external_reference ?? '—' }}</td></tr>
                        <tr><th>Attempt (payment #{{ $payment->id }})</th><td>{{ $payment->created_at->format('M j, Y g:i A') }}</td></tr>
                        @if($payment->paid_at)
                            <tr><th>Paid at</th><td>{{ $payment->paid_at->format('M j, Y g:i A') }}</td></tr>
                        @endif
                        @if($payment->failure_reason)
                            <tr><th>Last issue</th><td class="text-muted">{{ $payment->failure_reason }}</td></tr>
                        @endif
                    </table>
                @empty
                    <div class="text-center py-3 text-muted">No payment attempts have been made for this order.</div>
                @endforelse
            </div>
        </div>

        @if($order->purchases->isNotEmpty())
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Purchases (entitlements)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Book</th>
                                    <th scope="col" style="width: 140px;" class="text-right">Amount</th>
                                    <th scope="col" style="width: 170px;">Purchased at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->purchases as $purchase)
                                    <tr>
                                        <td>
                                            <a href="{{ route('books.show', $purchase->book) }}">{{ $purchase->book->title }}</a>
                                        </td>
                                        <td class="text-right">{{ \App\Support\Money::format($purchase->amount, $purchase->currency) }}</td>
                                        <td>{{ optional($purchase->purchased_at)->format('M j, Y g:i A') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="alert alert-secondary" role="alert">
            <i class="fas fa-info-circle mr-1"></i>
            This view is read-only. Refunds and manual status changes arrive in a later stage.
        </div>
    </div>
@endsection
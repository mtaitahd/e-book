@extends('layouts.admin.app')

@php
    $pageTitle = $currentStatus === null ? 'Orders' : ucfirst($currentStatus) . ' Orders';
@endphp

@section('title', $pageTitle)
@section('heading', $pageTitle)

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                {{ $orders->total() }} order{{ $orders->total() === 1 ? '' : 's' }} found
            </div>
            @if($currentStatus === \App\Models\Order::STATUS_PENDING)
                <form method="POST" action="{{ route('admin.orders.bulk-destroy') }}" id="bulkDeleteForm"
                      class="ml-auto"
                      onsubmit="return confirm('Permanently delete the selected pending orders? This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm" data-bulk-delete disabled>
                        <i class="fas fa-trash"></i> Delete selected (<span data-bulk-count>0</span>)
                    </button>
                </form>
            @endif
        </div>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $currentStatus === null ? 'active' : '' }}" href="{{ $statusLinks['all'] }}">All</a>
            </li>
            @foreach($statuses as $status)
                <li class="nav-item">
                    <a class="nav-link {{ $currentStatus === $status ? 'active' : '' }}"
                       href="{{ $statusLinks[$status] }}">
                        {{ ucfirst($status) }}
                    </a>
                </li>
            @endforeach
        </ul>

        @if($orders->isEmpty())
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <div class="text-center">
                        <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                        <p class="mb-1 text-gray-800">No orders found{{ $currentStatus ? ' with this status' : '' }}.</p>
                        <p class="small text-muted mb-0">Orders appear here once customers check out.</p>
                    </div>
                </div>
            </div>
        @else
            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    @if($currentStatus === \App\Models\Order::STATUS_PENDING)
                                        <th scope="col" style="width: 40px;">
                                            <input type="checkbox" id="orderSelectAll"
                                                   aria-label="Select all pending orders">
                                        </th>
                                    @endif
                                    <th scope="col">Order</th>
                                    <th scope="col">Customer</th>
                                    <th scope="col" style="width: 120px;">Items</th>
                                    <th scope="col" style="width: 130px;">Total</th>
                                    <th scope="col" style="width: 120px;">Status</th>
                                    <th scope="col" style="width: 160px;">Placed</th>
                                    <th scope="col" style="width: 120px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orders as $order)
                                    <tr>
                                        @if($currentStatus === \App\Models\Order::STATUS_PENDING)
                                            <td>
                                                @if($order->isPending())
                                                    <input type="checkbox" name="ids[]" value="{{ $order->id }}"
                                                           form="bulkDeleteForm" data-order-checkbox
                                                           aria-label="Select {{ $order->order_number }}">
                                                @endif
                                            </td>
                                        @endif
                                        <td>{{ $order->order_number }}</td>
                                        <td>
                                            {{ $order->user->name }}
                                            <span class="d-block small text-muted">{{ $order->user->email }}</span>
                                        </td>
                                        <td>{{ $order->items->count() }}</td>
                                        <td>{{ \App\Support\Money::format($order->total, $order->currency) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $order->statusBadge() }}">{{ ucfirst($order->status) }}</span>
                                        </td>
                                        <td>{{ $order->created_at->format('M j, Y g:i A') }}</td>
                                        <td>
                                            <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-info btn-sm" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            @if($order->isPending())
                                                <form method="POST" action="{{ route('admin.orders.destroy', $order) }}"
                                                      class="d-inline"
                                                      onsubmit="return confirm('Permanently delete order {{ $order->order_number }}? This cannot be undone.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    {{ $orders->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    @if($currentStatus === \App\Models\Order::STATUS_PENDING && $orders->isNotEmpty())
        <script>
            (function () {
                var selectAll = document.getElementById('orderSelectAll');
                var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-order-checkbox]'));
                var button = document.querySelector('[data-bulk-delete]');
                var count = document.querySelector('[data-bulk-count]');

                if (!selectAll || !button || boxes.length === 0) {
                    return;
                }

                function refresh() {
                    var checked = boxes.filter(function (box) { return box.checked; }).length;
                    button.disabled = checked === 0;
                    count.textContent = checked;
                    selectAll.indeterminate = checked > 0 && checked < boxes.length;
                    selectAll.checked = checked === boxes.length;
                }

                selectAll.addEventListener('change', function () {
                    boxes.forEach(function (box) { box.checked = selectAll.checked; });
                    refresh();
                });

                boxes.forEach(function (box) { box.addEventListener('change', refresh); });
            })();
        </script>
    @endif
@endpush
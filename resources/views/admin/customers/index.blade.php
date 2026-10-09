@extends('layouts.admin.app')

@section('title', 'Manage Customers')
@section('heading', 'Customers')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                {{ $customers->total() }} customer{{ $customers->total() === 1 ? '' : 's' }} found
            </div>
        </div>

        @if($customers->isEmpty())
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-1 text-gray-800">No customers yet.</p>
                    <p class="small text-muted mb-0">Customers appear here as soon as they register on the store.</p>
                </div>
            </div>
        @else
            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Phone</th>
                                    <th scope="col" style="width: 110px;">Status</th>
                                    <th scope="col" style="width: 90px;">Orders</th>
                                    <th scope="col" style="width: 90px;">Books</th>
                                    <th scope="col" style="width: 130px;">Joined</th>
                                    <th scope="col" style="width: 120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customers as $customer)
                                    <tr>
                                        <td>
                                            <span class="d-block">{{ $customer->name }}</span>
                                            <span class="d-block small text-muted">{{ $customer->id }}</span>
                                        </td>
                                        <td>{{ $customer->email }}</td>
                                        <td>{{ $customer->phone ?: '—' }}</td>
                                        <td>
                                            @if($customer->status === 'active')
                                                <span class="badge badge-success">Active</span>
                                            @elseif($customer->status === 'suspended')
                                                <span class="badge badge-danger">Suspended</span>
                                            @else
                                                <span class="badge badge-secondary">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $customer->orders_count }}</td>
                                        <td>{{ $customer->purchases_count }}</td>
                                        <td>{{ $customer->created_at?->format('M j, Y') ?: '—' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Permanently delete this customer? This cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete permanently"
                                                        @if($customer->orders_count > 0 || $customer->purchases_count > 0) disabled @endif>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            @if($customer->orders_count > 0 || $customer->purchases_count > 0)
                                                <span class="d-inline-block ml-1 small text-muted" title="Orders or purchases must be kept for sales history">
                                                    <i class="fas fa-lock"></i>
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    {{ $customers->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
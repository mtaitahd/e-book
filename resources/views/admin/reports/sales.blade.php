@extends('layouts.admin.app')

@section('title', 'Sales & Revenue Report')
@section('heading', 'Sales & Revenue Report')

@section('content')
    <div class="container-fluid">

        {{-- ------------------------------------------------------------------
             One filter drives the entire page. Every widget below reads the
             same resolved period, so the cards, the chart and the tables can
             never describe different windows.
        ------------------------------------------------------------------ --}}
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('admin.reports.sales') }}" class="form-row align-items-end">
                    <div class="form-group col-12 col-sm-6 col-md-3">
                        <label for="range" class="small font-weight-bold">Period</label>
                        <select id="range" name="range" class="form-control form-control-sm" onchange="this.form.submit()">
                            @foreach($presets as $value => $label)
                                <option value="{{ $value }}" @selected($period->preset === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group col-6 col-md-3">
                        <label for="from" class="small font-weight-bold">From</label>
                        <input id="from" name="from" type="date" value="{{ $period->fromDate() }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="form-group col-6 col-md-3">
                        <label for="to" class="small font-weight-bold">To</label>
                        <input id="to" name="to" type="date" value="{{ $period->toDate() }}"
                               class="form-control form-control-sm">
                    </div>

                    <div class="form-group col-12 col-md-3">
                        <div class="btn-group btn-group-sm d-flex" role="group">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-fw fa-filter"></i> Apply
                            </button>
                            <a href="{{ route('admin.reports.sales') }}" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </div>
                </form>

                <p class="small text-muted mb-0 mt-2">
                    Showing <strong>{{ $period->label() }}</strong>
                    ({{ $period->from->format('j M Y') }} to {{ $period->to->format('j M Y') }}, inclusive).
                    All dates are in <strong>{{ $period->timezoneName() }}</strong> server time.
                </p>
            </div>
        </div>

        {{-- ------------------------------------------------------------------
             1. REVENUE — paid orders only. A pending, failed or cancelled
                order is never counted here.
        ------------------------------------------------------------------ --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <h6 class="text-uppercase font-weight-bold text-gray-700 mb-0">Revenue</h6>
            <a href="{{ $exportUrl }}" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-fw fa-file-csv"></i> Export paid orders (CSV)
            </a>
        </div>

        @unless($summary['total_orders'] > 0)
            <div class="alert alert-light border mb-4" role="status">
                <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                <strong>No sales were recorded in this period.</strong>
                Revenue is counted from paid orders only, so a store with no
                completed payments legitimately reports zero. Checkout attempts
                that were never paid are shown separately under order activity
                below.
            </div>
        @endunless

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total revenue</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    @forelse($summary['rows'] as $row)
                                        {{ $row['revenue_formatted'] }}<br>
                                    @empty
                                        {{ \App\Support\Money::format('0.00', $summary['store_currency']) }}
                                    @endforelse
                                </div>
                                <div class="small text-muted mt-1">Paid orders only</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-coins fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Paid orders</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $summary['total_orders'] }}</div>
                                <div class="small text-muted mt-1">
                                    @if($averageOrderValue)
                                        Avg {{ implode(', ', $averageOrderValue) }} per order
                                    @endif
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-receipt fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Books sold</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $copiesSold }}</div>
                                <div class="small text-muted mt-1">Total copies delivered</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-book fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Customers</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $summary['total_customers'] }}</div>
                                <div class="small text-muted mt-1">Unique paying accounts</div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($summary['other_currencies'])
            <div class="alert alert-warning small" role="alert">
                <strong>Mixed currencies present.</strong> Totals are never converted or added together
                across currencies. This period also contains paid orders in
                @foreach($summary['other_currencies'] as $currency => $amount)
                    {{ $currency }} ({{ \App\Support\Money::format($amount, $currency) }})@if(! $loop->last), @endif
                @endforeach.
            </div>
        @endif

        {{-- ------------------------------------------------------------------
             2. REVENUE TREND — rendered as inline SVG, so it scales down to a
                phone with no JavaScript and no new charting dependency.
                A zero here is a fact: the day happened and nothing sold.
        ------------------------------------------------------------------ --}}
        <div class="card shadow mb-4">
            <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center">
                <span class="font-weight-bold">Revenue trend</span>
                <span class="small text-muted">
                    By {{ $trend['buckets_by'] }} &middot; {{ $summary['store_currency'] }} &middot; {{ count($trend['points']) }} points
                </span>
            </div>
            <div class="card-body">
                @if($chart->isEmpty())
                    <div class="text-center py-5">
                        <i class="fas fa-chart-line fa-3x text-gray-300 mb-3"></i>
                        <p class="mb-1 text-gray-800">No paid orders in this period.</p>
                        <p class="small text-muted mb-0">
                            The chart fills in automatically as orders are paid.
                        </p>
                    </div>
                @else
                    <div class="revenue-chart-wrapper">
                        {!! $chart->render() !!}
                    </div>

                    <details class="mt-3">
                        <summary class="small text-muted">View trend as a data table</summary>
                        <div class="table-responsive mt-2">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th scope="col">Period</th>
                                        <th scope="col" class="text-right">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($trend['points'] as $point)
                                        <tr>
                                            <td>{{ $point['label'] }}</td>
                                            <td class="text-right">{{ $point['formatted'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>
        </div>

        {{-- ------------------------------------------------------------------
             3. ORDER ACTIVITY — deliberately not revenue. Counts every order
                PLACED in the period, so an abandoned checkout is visible
                without ever inflating the revenue figure.
        ------------------------------------------------------------------ --}}
        <div class="card shadow mb-4">
            <div class="card-header py-2 font-weight-bold">Order activity</div>
            <div class="card-body">
                <p class="small text-muted">
                    Orders <em>placed</em> in this period, by status. Only <strong>Paid</strong> is revenue;
                    the other rows are store activity and are never added to the total.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Status</th>
                                <th scope="col" class="text-right">Orders</th>
                                <th scope="col" class="text-right">Order value</th>
                                <th scope="col" class="text-right">Counts as revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($statusSummary as $row)
                                <tr>
                                    <td>
                                        <span class="badge badge-{{ $row['badge'] }}">{{ $row['label'] }}</span>
                                    </td>
                                    <td class="text-right">{{ $row['orders'] }}</td>
                                    <td class="text-right">{{ $row['formatted'] ?: '—' }}</td>
                                    <td class="text-right">
                                        @if($row['is_revenue'])
                                            <span class="badge badge-success">Yes</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ------------------------------------------------------------------
             4. PAYMENT CHANNEL — read from the channel value Abliner actually
                stored. Nothing is inferred from a phone number, and if the
                store has no payment rows the card says so instead of drawing
                an empty chart.
        ------------------------------------------------------------------ --}}
        <div class="card shadow mb-4">
            <div class="card-header py-2 font-weight-bold">Payment channel</div>
            <div class="card-body">
                @if(! $channels['available'])
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                        <strong>Not available yet.</strong> {{ $channels['reason'] }}
                    </p>
                @elseif(empty($channels['rows']))
                    <p class="text-muted mb-0">
                        <i class="fas fa-info-circle text-gray-400 mr-1"></i>
                        No completed payments were recorded in this period.
                    </p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Channel</th>
                                    <th scope="col" class="text-right">Payments</th>
                                    <th scope="col" class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($channels['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-right">{{ $row['payments'] }}</td>
                                        <td class="text-right">{{ $row['amount_formatted'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        Amounts are totals of the payment attempts that completed, not order revenue.
                    </p>
                @endif
            </div>
        </div>

        {{-- ------------------------------------------------------------------
             5. BREAKDOWNS
        ------------------------------------------------------------------ --}}
        <div class="row">
            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-2 font-weight-bold">Top books</div>
                    <div class="card-body p-0">
                        @if(empty($topBooks))
                            <p class="text-muted text-center py-4 mb-0">No books sold in this period.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col">Book</th>
                                            <th scope="col" class="text-right">Copies</th>
                                            <th scope="col" class="text-right">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topBooks as $book)
                                            <tr>
                                                <td>{{ $book['title'] }}</td>
                                                <td class="text-right">{{ $book['copies'] }}</td>
                                                <td class="text-right">{{ $book['revenue_formatted'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <p class="small text-muted mb-0 p-2">
                                Revenue uses the price recorded on the order at the time of sale, not the
                                book's current price.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header py-2 font-weight-bold">Best customers</div>
                    <div class="card-body p-0">
                        @if(empty($topCustomers))
                            <p class="text-muted text-center py-4 mb-0">No paying customers in this period.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th scope="col">Customer</th>
                                            <th scope="col" class="text-right">Orders</th>
                                            <th scope="col" class="text-right">Copies</th>
                                            <th scope="col" class="text-right">Spent</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($topCustomers as $customer)
                                            <tr>
                                                <td>{{ $customer['name'] }}</td>
                                                <td class="text-right">{{ $customer['orders'] }}</td>
                                                <td class="text-right">{{ $customer['copies'] }}</td>
                                                <td class="text-right">{{ $customer['spent_formatted'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-2 font-weight-bold">Sales by category</div>
            <div class="card-body p-0">
                @if(empty($topCategories))
                    <p class="text-muted text-center py-4 mb-0">No categorised sales in this period.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Category</th>
                                    <th scope="col" class="text-right">Copies</th>
                                    <th scope="col" class="text-right">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($topCategories as $category)
                                    <tr>
                                        <td>{{ $category['name'] }}</td>
                                        <td class="text-right">{{ $category['copies'] }}</td>
                                        <td class="text-right">{{ $category['revenue_formatted'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mb-0 p-2">
                        <strong>These figures are not additive.</strong> A book that belongs to more than
                        one category is counted in each of them, so this table can total more than the
                        revenue above. It describes category interest, not a split of earnings.
                    </p>
                @endif
            </div>
        </div>

        {{-- ------------------------------------------------------------------
             6. RECENT SALES — the same paid-order set, paginated and linked to
                the existing order detail page.
        ------------------------------------------------------------------ --}}
        <div class="card shadow mb-4">
            <div class="card-header py-2 font-weight-bold">Recent sales</div>
            @if($recentSales->isEmpty())
                <div class="card-body text-center py-5">
                    <i class="fas fa-receipt fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-1 text-gray-800">No sales in this period.</p>
                    <p class="small text-muted mb-0">Try widening the date range above.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th scope="col">Order</th>
                                <th scope="col">Customer</th>
                                <th scope="col" class="text-right">Items</th>
                                <th scope="col" class="text-right">Total</th>
                                <th scope="col">Paid</th>
                                <th scope="col" style="width: 80px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentSales as $order)
                                <tr>
                                    <td>{{ $order->order_number }}</td>
                                    <td>{{ $order->user?->name }}</td>
                                    <td class="text-right">{{ $order->item_count }}</td>
                                    <td class="text-right">{{ \App\Support\Money::format($order->total, $order->currency) }}</td>
                                    <td>{{ $order->paid_at?->format('M j, Y g:i A') }}</td>
                                    <td>
                                        <a href="{{ route('admin.orders.show', $order) }}" class="btn btn-info btn-sm" title="View order">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    {{ $recentSales->onEachSide(1)->links() }}
                </div>
            @endif
        </div>

        <p class="small text-muted">
            <strong>How these numbers are built.</strong>
            Revenue, paid orders, books sold, customers, the trend, the
            breakdowns and the CSV export all read the same paid orders for the
            same inclusive date range, and every total is summed by the database
            rather than in PHP. A sale is dated by the moment it was paid.
            Checkout attempts, carts and download entitlements are not revenue.
        </p>
    </div>
@endsection

@push('styles')
    <style>
        .revenue-chart-wrapper {
            width: 100%;
            overflow-x: auto;
        }
        .revenue-chart {
            display: block;
            min-width: 320px;
            width: 100%;
            height: auto;
        }
        details > summary {
            cursor: pointer;
        }
    </style>
@endpush

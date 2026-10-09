<ul class="navbar-nav sidebar sidebar-light accordion" id="accordionSidebar">
    <a class="sidebar-brand d-flex align-items-center justify-content-center" style="background-color: rgb(114, 87, 139);" href="{{ route('admin.dashboard') }}">
        <div class="sidebar-brand-icon">
            <img src="{{ asset('assets/e_book-removebg-preview.png') }}" alt="E-Book" style="width: 2em; height: auto;">
        </div>
        <div class="sidebar-brand-text mx-3 text-white">E-Book</div>
    </a>
    <hr class="sidebar-divider my-0">

    <li class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-fw fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Catalogue</div>

    {{-- Every entry below is a page of its own, reachable directly from here. --}}
    <li class="nav-item {{ request()->routeIs('admin.books*') ? 'active' : '' }}">
    <li class="nav-item {{ request()->routeIs('admin.books*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.books.index') }}">
            <i class="fas fa-fw fa-book-open"></i>
            <span>Books</span>
        </a>
    </li>
    <li class="nav-item {{ request()->routeIs('admin.authors*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.authors.index') }}">
            <i class="fas fa-fw fa-pen-nib"></i>
            <span>Authors</span>
        </a>
    </li>
    <li class="nav-item {{ request()->routeIs('admin.categories*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.categories.index') }}">
            <i class="fas fa-fw fa-tags"></i>
            <span>Categories</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Sales</div>

    <li class="nav-item {{ request()->routeIs('admin.orders.index') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.orders.index') }}">
            <i class="fas fa-fw fa-receipt"></i>
            <span>All Orders</span>
        </a>
    </li>
    <li class="nav-item {{ request()->routeIs('admin.orders.pending') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.orders.pending') }}">
            <i class="fas fa-fw fa-hourglass-half"></i>
            <span>Pending Orders</span>
        </a>
    </li>
    <li class="nav-item {{ request()->routeIs('admin.orders.paid') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.orders.paid') }}">
            <i class="fas fa-fw fa-check-circle"></i>
            <span>Paid Orders</span>
        </a>
    </li>

    {{-- The people who buy: visible so an administrator can see the customer
         base and remove registrations that never engaged. --}}
    <li class="nav-item {{ request()->routeIs('admin.customers*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.customers.index') }}">
            <i class="fas fa-fw fa-users"></i>
            <span>Customers</span>
        </a>
    </li>

    {{-- Stage 10: revenue reporting, read from genuinely paid orders only. --}}
    <li class="nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.reports.sales') }}">
            <i class="fas fa-fw fa-chart-line"></i>
            <span>Sales Reports</span>
        </a>
    </li>

    {{-- The store's own money: live Abliner balance and payouts. Separate from
         Sales Reports, which reports what was earned, not what is still held. --}}
    <li class="nav-item {{ request()->routeIs('admin.wallet.*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.wallet.index') }}">
            <i class="fas fa-fw fa-wallet"></i>
            <span>Wallet &amp; Withdrawals</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Settings</div>

    {{-- Stage 10.1: read-only view of the payment provider configuration. --}}
    <li class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.settings.payments') }}">
            <i class="fas fa-fw fa-credit-card"></i>
            <span>Payment Settings</span>
        </a>
    </li>

    <li class="nav-item {{ request()->routeIs('admin.subscribers.*') ? 'active' : '' }}">
        <a class="nav-link" href="{{ route('admin.subscribers.index') }}">
            <i class="fas fa-fw fa-envelope"></i>
            <span>Subscribers</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="sidebar-heading">Web</div>
    <li class="nav-item">
        <a class="nav-link" href="{{ route('home') }}">
            <i class="fas fa-fw fa-store"></i>
            <span>Storefront</span>
        </a>
    </li>

    <hr class="sidebar-divider">
    <div class="version" id="version-ruangadmin">E-Book Admin :: RuangAdmin</div>
</ul>

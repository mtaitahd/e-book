<nav class="account-nav" aria-label="Account">
    <a href="{{ route('account.show') }}" class="{{ request()->routeIs('account.show') ? 'is-active' : '' }}">Account</a>
    <a href="{{ route('account.orders.index') }}" class="{{ request()->routeIs('account.orders.index') || request()->routeIs('account.orders.show') ? 'is-active' : '' }}">Orders</a>
    <a href="{{ route('account.purchases.index') }}" class="{{ request()->routeIs('account.purchases.index') || request()->routeIs('account.purchases.show') || request()->routeIs('account.purchases.read') ? 'is-active' : '' }}">My Library</a>
</nav>
<header class="ebs-header">
    {{-- ===================== Top bar ===================== --}}
    <div class="ebs-topbar">
        <div class="container ebs-topbar__inner">
            <button
                type="button"
                class="ebs-mobile-toggle"
                id="ebsMobileToggle"
                aria-label="Open navigation"
                aria-expanded="false"
                aria-controls="ebsMobileDrawer"
            >
                &#9776;
            </button>

            <a class="ebs-logo" href="{{ route('home') }}" aria-label="E-Book home">
                <img src="{{ asset('assets/e_book-removebg-preview.png') }}" alt="E-Book" width="28" height="28" loading="lazy">
                <span>e-books &amp; PDF</span>
            </a>

            <nav class="ebs-topnav" aria-label="Primary">
                {{-- Keep Menu as the final item on the left side of the desktop header. --}}
                <button
                    type="button"
                    class="ebs-topnav__toggle"
                    aria-label="Open navigation"
                    aria-expanded="false"
                    aria-haspopup="dialog"
                    aria-controls="ebsDrawer"
                    data-drawer-open="ebsDrawer"
                >
                    <span class="ebs-topnav__burger" aria-hidden="true">&#9776;</span>
                    <span>Menu</span>
                </button>
            </nav>

            <form class="ebs-search" id="ebsSearchForm" action="{{ route('books.index') }}" method="get" role="search">
                <label for="ebsSearchInput" class="sr-only" style="position:absolute;left:-9999px;">Search books</label>

                <div class="ebs-search__cat">
                    <button
                        type="button"
                        class="ebs-search__cat-btn"
                        id="ebsSearchCatToggle"
                        aria-haspopup="menu"
                        aria-expanded="false"
                        aria-controls="ebsSearchCatMenu"
                    >
                        <span class="ebs-search__cat-name" data-search-cat-name>Books</span>
                        <svg class="ebs-search__cat-chevron" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                    <ul class="ebs-search__cat-menu" id="ebsSearchCatMenu" role="menu" aria-label="Search department" data-search-cat-menu hidden>
                        <li role="none">
                            <button
                                type="button"
                                class="ebs-search__cat-option"
                                role="menuitemradio"
                                aria-checked="true"
                                data-search-option
                                data-name="Books"
                                data-url="{{ route('books.index') }}"
                            >
                                Books
                            </button>
                        </li>
                        @foreach($navCategories ?? [] as $category)
                            <li role="none">
                                <button
                                    type="button"
                                    class="ebs-search__cat-option"
                                    role="menuitemradio"
                                    aria-checked="false"
                                    data-search-option
                                    data-name="{{ $category->name }}"
                                    data-url="{{ route('categories.show', $category) }}"
                                >
                                    {{ $category->name }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <input
                    id="ebsSearchInput"
                    class="ebs-search__input"
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search books..."
                    autocomplete="off"
                    aria-label="Search books"
                >
                <button class="ebs-search__btn" type="submit" aria-label="Search">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                </button>
            </form>

            <a class="ebs-cart" href="{{ route('cart.show') }}" aria-label="Shopping cart, {{ app(\App\Services\CartService::class)->count() }} items">
                <span class="ebs-cart__icon-wrap">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/>
                        <path d="M2.5 3h2l2.6 12.4a1.5 1.5 0 0 0 1.5 1.1h10.4a1.5 1.5 0 0 0 1.5-1.2L22 7H6"/>
                    </svg>
                    <span class="ebs-cart__count">{{ app(\App\Services\CartService::class)->count() }}</span>
                </span>
                <span class="ebs-cart__label">
                    <small>Cart</small>
                    ({{ app(\App\Services\CartService::class)->count() }})
                </span>
            </a>

            <div class="ebs-account" id="ebsAccountMenu">
                <div class="ebs-account__trigger" aria-haspopup="menu" aria-expanded="false" aria-controls="ebsAccountPanel">
                    @guest
                        <div>
                            <span class="ebs-account__name">Hello, sign in</span>
                            <a class="ebs-account__signin" href="{{ route('login') }}" data-ebs-auth-open="login">Sign in</a>
                        </div>
                    @else
                        <div>
                            <span class="ebs-account__name">Account</span>
                            <a href="{{ route('account.show') }}">Hello, {{ \Illuminate\Support\Str::limit(auth()->user()->name, 14) }}</a>
                        </div>
                    @endguest
                </div>

                <div class="ebs-account__panel" id="ebsAccountPanel" hidden>
                    @guest
                        <p class="ebs-account__greet">Hello, sign in</p>
                        <a class="ebs-account__cta" href="{{ route('login') }}" data-ebs-auth-open="login">Sign in</a>
                        <p class="ebs-account__hint">New to E-Book? <a href="{{ route('books.index') }}">Browse the store</a></p>
                        <div class="ebs-account__cols">
                            <div class="ebs-account__col">
                                <h3 class="ebs-account__col-head">Your Lists</h3>
                                <a href="{{ route('login') }}" data-ebs-auth-open="login">Your Books</a>
                            </div>
                            <div class="ebs-account__col">
                                <h3 class="ebs-account__col-head">Your Account</h3>
                                <a href="{{ route('login') }}" data-ebs-auth-open="login">Account</a>
                                <a href="{{ route('login') }}" data-ebs-auth-open="login">Orders</a>
                            </div>
                        </div>
                    @else
                        <p class="ebs-account__greet">Hello, {{ \Illuminate\Support\Str::limit(auth()->user()->name, 20) }}</p>
                        <a class="ebs-account__cta" href="{{ route('account.show') }}">Your Account</a>
                        <div class="ebs-account__cols">
                            <div class="ebs-account__col">
                                <h3 class="ebs-account__col-head">Your Lists</h3>
                                <a href="{{ route('account.purchases.index') }}">Your Books</a>
                            </div>
                            <div class="ebs-account__col">
                                <h3 class="ebs-account__col-head">Your Account</h3>
                                <a href="{{ route('account.show') }}">Account</a>
                                <a href="{{ route('account.orders.index') }}">Orders</a>
                                @if(auth()->user()->isAdmin())
                                    <a href="{{ route('admin.dashboard') }}">Admin</a>
                                @endif
                            </div>
                        </div>
                        <form class="ebs-account__logout" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit">Logout</button>
                        </form>
                    @endguest
                </div>
            </div>

            <a class="ebs-returns" href="{{ auth()->check() ? route('account.orders.index') : route('login') }}"
                @guest data-ebs-auth-open="login" @endguest>
                <span>Returns</span>
                <span>&amp; Orders</span>
            </a>
        </div>
    </div>

    {{-- The standalone "Books / Shop Books / Categories / Your Books" bar was
         removed: it duplicated the sticky topbar directly above it, which already
         carries Books, a Categories dropdown and the account menu (where Your
         Books lives). One bar, less to scroll past. --}}

    {{-- ===================== Categories side drawer ===================== --}}
    <div class="ebs-drawer" id="ebsDrawer" tabindex="-1" role="dialog" aria-modal="true" aria-label="Store navigation" aria-hidden="true">
        <div class="ebs-drawer__head">
            <a class="ebs-drawer__logo" href="{{ route('home') }}" aria-label="E-Book home">
                <img src="{{ asset('assets/e_book-removebg-preview.png') }}" alt="E-Book" width="22" height="22" loading="lazy">
                <span>e-books &amp; PDF</span>
            </a>
            <button
                type="button"
                class="ebs-drawer__close"
                id="ebsDrawerClose"
                aria-label="Close navigation drawer"
            >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>

        <div class="ebs-drawer__body">
            <div class="ebs-drawer__greeting">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"/>
                    <path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
                @auth
                    <span>Hello, {{ \Illuminate\Support\Str::limit(auth()->user()->name, 24) }}</span>
                @else
                    <span>Hello, <a href="{{ route('login') }}" data-ebs-auth-open="login">sign in</a></span>
                @endauth
            </div>

            <p class="ebs-drawer__label" id="ebsDrawerTitle">Browse Categories</p>

            <nav class="ebs-drawer__nav" aria-label="Categories">
                <a href="{{ route('books.index') }}">All Books <span class="ebs-drawer__arrow" aria-hidden="true">&#8250;</span></a>
                @foreach($navCategories ?? [] as $category)
                    <a href="{{ route('categories.show', $category) }}">
                        {{ $category->name }}<span class="ebs-drawer__arrow" aria-hidden="true">&#8250;</span>
                    </a>
                @endforeach
            </nav>

            <hr class="ebs-drawer__divider">

            <nav class="ebs-drawer__nav" aria-label="Browse store">
                <a href="{{ route('books.index') }}">Browse</a>
                <a href="{{ route('home') }}#latest">Latest Books</a>
                @auth
                    <a href="{{ route('account.purchases.index') }}">My Books</a>
                @else
                    <a href="{{ route('login') }}" data-ebs-auth-open="login">My Books</a>
                @endauth
            </nav>
        </div>
    </div>

    <div class="ebs-drawer-overlay" id="ebsDrawerOverlay" data-drawer-close aria-hidden="true" tabindex="-1"></div>

    {{-- ===================== Mobile drawer ===================== --}}
    <div class="ebs-mobile" id="ebsMobileDrawer" aria-label="Mobile navigation">
        <div class="container">
            <form class="ebs-mobile__search" action="{{ route('books.index') }}" method="get" role="search">
                <input
                    class="ebs-search__input"
                    type="search"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search books..."
                    aria-label="Search books"
                >
                <button class="ebs-search__btn" type="submit" aria-label="Search">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                </button>
            </form>

            <nav class="ebs-mobile__nav" aria-label="Mobile">
                <span class="ebs-mobile__label">Shop</span>
                <a href="{{ route('books.index') }}">Books</a>
                @foreach($navCategories ?? [] as $category)
                    <a href="{{ route('categories.show', $category) }}">{{ $category->name }}</a>
                @endforeach

                <span class="ebs-mobile__label">Account</span>
                @auth
                    <a href="{{ route('account.show') }}">Account</a>
                    <a href="{{ route('account.orders.index') }}">Orders</a>
                    <a href="{{ route('account.purchases.index') }}">Your Books</a>
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.dashboard') }}">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="ebs-account__btn" type="submit" style="text-align:left;padding:10px 4px;font-size:.98rem;">Logout</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" data-ebs-auth-open="login">Sign in</a>
                    <a href="{{ route('books.index') }}">Browse Books</a>
                @endauth
            </nav>
        </div>
    </div>
</header>

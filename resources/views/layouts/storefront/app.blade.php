<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'E-Book')</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/e_book-removebg-preview.png?v=20261005') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/e_book-removebg-preview.png?v=20261005') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/book3d.css') }}">
    @stack('styles')
</head>
<body>
    @include('layouts.partials.storefront-header')

    {{-- `site-main--art` carries the brand artwork behind the whole home page. --}}
    <main class="site-main{{ request()->routeIs('home') ? ' site-main--art' : '' }}">
        <div class="container">
            @include('partials.swal-flash')

            @yield('content')
        </div>
    </main>

    @include('layouts.partials.storefront-footer')

    @include('layouts.partials.auth-modals')

    <script src="{{ asset('assets/auth/js/sweetalert.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/app.js') }}"></script>
    <script src="{{ asset('assets/shared/swal-flash.js') }}"></script>
    @stack('scripts')
</body>
</html>

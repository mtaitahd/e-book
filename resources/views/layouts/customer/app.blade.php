<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Account') | E-Book</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/e_book-removebg-preview.png?v=20261005') }}">
    <link rel="apple-touch-icon" href="{{ asset('assets/e_book-removebg-preview.png?v=20261005') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/app.css') }}">
    @stack('styles')
</head>
<body class="@yield('body_class')">
    {{-- The reader is a dedicated reading app: it opts out of the storefront
         chrome entirely instead of merely hiding it with CSS. --}}
    @if (! view()->hasSection('hide_site_chrome'))
        @include('layouts.partials.storefront-header')
    @endif

    <main class="site-main">
        <div class="container">
            @include('partials.swal-flash')

            @yield('content')
        </div>
    </main>

    @if (! view()->hasSection('hide_site_chrome'))
        @include('layouts.partials.storefront-footer')
    @endif

    <script src="{{ asset('assets/auth/js/sweetalert.min.js') }}"></script>
    <script src="{{ asset('assets/storefront/js/app.js') }}"></script>
    <script src="{{ asset('assets/shared/swal-flash.js') }}"></script>
    @stack('scripts')
</body>
</html>

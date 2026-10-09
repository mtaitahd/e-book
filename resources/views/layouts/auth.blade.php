<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title', 'E-Book')</title>
    <link rel="stylesheet" href="{{ asset('assets/auth/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/auth/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/auth/css/styles.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/fonts.css') }}">
    @stack('styles')
</head>
<body>
    <div class="login shadow container-fluid d-flex flex-wrap justify-content-center align-items-center">
        <div id="headers">
            <h3>E-BOOK</h3>
            <div class="brand-icon">
                <img src="{{ asset('assets/e_book-removebg-preview.png') }}" alt="E-Book" style="width: 3.5rem; height: auto;">
            </div>
            <p class="tagline">E-Book Sales &amp; Management</p>
            <p class="muted-text">Browse, purchase and manage your digital books in one place.</p>
        </div>

        <div id="contents">
            <p class="panel-title">@yield('panel-title')</p>

            @include('partials.swal-flash')

            @yield('content')
        </div>
    </div>

    <style>
        body {
            background: url('{{ asset("assets/auth/img/cover.jpg") }}');
            background-size: cover;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 0;
        }
        .login {
            min-height: 100vh;
            padding: 24px;
            background-color: rgba(20, 20, 30, 0.55);
            gap: 0;
        }
        .shadow { box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        #headers, #contents { padding: 20px; box-shadow: 0 4px 8px rgb(21, 18, 68); }
        #headers {
            width: 30%;
            background-color: #ff9900;
            text-align: center;
            min-height: 450px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        #headers h3 { color: #111820; font-weight: bold; letter-spacing: .1em; }
        #headers .brand-icon { font-size: 3.5rem; color: #111820; margin: 24px 0 12px; }
        #headers .tagline { color: #111820; font-weight: 700; }
        #headers .muted-text { color: rgba(17,24,32,.82); font-size: .95rem; padding: 0 24px; }
        #contents {
            width: 40%;
            background-color: #fff;
            padding: 30px;
            min-height: 450px;
        }
        #contents .panel-title { text-align: center; font-size: 1.3rem; font-weight: bold; margin-bottom: 18px; }
        #contents label { display: block; font-size: .95rem; margin: 14px 0 4px; color: #333; }
        #contents input {
            border: 2px solid #ff9900;
            width: 100%;
            padding: 12px 14px;
            border-radius: 0;
            font-size: .95rem;
        }
        #contents input:focus { outline: none; border-color: #ff9900; box-shadow: 0 0 0 3px rgba(255, 153, 0, .2); }
        #contents .btn {
            width: 100%;
            padding: 14px;
            background-color: #ff9900;
            border: 1px solid #ff9900;
            color: #111820;
            font-size: 16px;
            margin-top: 18px;
            border-radius: 0;
            cursor: pointer;
        }
        #contents .btn:hover { background-color: #e88900; border-color: #e88900; }
        #contents a { text-decoration: none; color: #b86d00; }
        #contents .alt { text-align: center; margin-top: 16px; font-size: .95rem; color: #444; }
        .errors { background: #fdecea; border: 1px solid #f5c6cb; color: #a94442; padding: 10px 14px; border-radius: 4px; margin-bottom: 14px; font-size: .92rem; }
        .errors ul { margin-left: 18px; }
        .success { background: #eaf6ec; border: 1px solid #c9e8cd; color: #2e7d32; padding: 10px 14px; border-radius: 4px; margin-bottom: 14px; }
        #contents input.is-invalid { border-color: #d9534f; box-shadow: 0 0 0 3px rgba(217, 83, 79, .18); }
        @keyframes ebs-swal-shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-8px); }
            40% { transform: translateX(7px); }
            60% { transform: translateX(-5px); }
            80% { transform: translateX(3px); }
        }
        form.has-swal-error { animation: ebs-swal-shake .45s ease-in-out; }
        @media (prefers-reduced-motion: reduce) {
            form.has-swal-error { animation: none; }
        }
        @media (max-width: 1024px) {
            #headers { width: 50%; min-height: auto; }
            #contents { width: 60%; }
        }
        @media (max-width: 768px) {
            #headers { width: 80%; min-height: auto; }
            #contents { width: 90%; margin-top: 20px; }
            .login { flex-direction: column; }
        }
        @media (max-width: 576px) {
            #headers, #contents { width: 100%; padding: 18px; }
        }
    </style>

    <script src="{{ asset('assets/auth/js/sweetalert.min.js') }}"></script>
    <script src="{{ asset('assets/shared/swal-flash.js') }}"></script>
</body>
</html>

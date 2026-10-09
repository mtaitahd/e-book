<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') | E-Book Admin</title>
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/fonts.css') }}">
    <link href="{{ asset('assets/admin/vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/admin/vendor/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/admin/css/ruang-admin.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/style.css') }}?v={{ filemtime(public_path('assets/admin/css/style.css')) }}">
    @stack('styles')
</head>
<body id="page-top">
    <div id="wrapper">
        @include('layouts.admin.sidebar')

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('layouts.admin.navbar')

                {{-- Everything below the topbar scrolls; the topbar and the sidebar
                     stay put. This is the scroll container the admin shell is built
                     around, so nothing inside it needs position:sticky. --}}
                <div id="adminScroll">
                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h4 class="mb-0 text-gray-800">@yield('heading', 'Dashboard')</h4>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Storefront</a></li>
                            <li class="breadcrumb-item active" aria-current="page">@yield('heading', 'Dashboard')</li>
                        </ol>
                    </div>
                </div>

                @if(session('success'))
    <div class="container-fluid">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
@endif

{{-- Controllers in this area bounce back with 'error' (a refused provider
     call, a failed rule) or 'info' (nothing to report yet). Rendering them
     here means no admin page can silently swallow its own failure. --}}
@if(session('error'))
    <div class="container-fluid">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
@endif

@if(session('info'))
    <div class="container-fluid">
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
@endif

@if($errors->any())
                    <div class="container-fluid">
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    </div>
                @endif

                @yield('content')
                </div>
            </div>
        </div>
    </div>

    {{-- Shared shell for every admin add/edit popup. --}}
    @include('layouts.admin._form_modal')

    <script src="{{ asset('assets/admin/vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/admin/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/admin/vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/ruang-admin.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/chapter-editor.js') }}"></script>
    <script src="{{ asset('assets/admin/js/book-pricing.js') }}"></script>
    <script src="{{ asset('assets/admin/js/book-type.js') }}"></script>
<script src="{{ asset('assets/admin/js/cover-preview.js') }}"></script>
<script src="{{ asset('assets/admin/js/admin-modal.js') }}"></script>

    @stack('scripts')
</body>
</html>

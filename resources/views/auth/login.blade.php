@extends('layouts.auth')

@section('title', 'E-Book - Admin Login')
@section('panel-title', 'Administrator Login')
@section('alert-title', 'Login Failed')
@section('alert-ok', 'Try Again')

@section('content')
    <p class="alt">This sign-in page is for store administrators. Customers sign in from the storefront.</p>

    <form method="POST" action="{{ route('admin.login.store') }}">
        @csrf

        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
            @if($errors->has('email')) class="is-invalid" aria-invalid="true" @endif>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" required autocomplete="current-password"
            @if($errors->has('password')) class="is-invalid" aria-invalid="true" @endif>

        <button type="submit" class="btn">Login</button>
    </form>

    <p class="alt"><a href="{{ route('home') }}">Back to the store</a></p>
@endsection

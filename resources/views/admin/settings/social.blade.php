@extends('layouts.admin.app')
@section('title', 'Social Media Links')
@section('heading', 'Social Media Links')
@section('content')
<div class="container-fluid"><div class="card shadow mb-4">
<div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Footer — Follow Us</h6></div>
<div class="card-body"><p class="text-muted">Enter the full link for each social media profile. Leave a field empty to hide its icon from the footer.</p>
<form method="POST" action="{{ route('admin.settings.social.update') }}">@csrf
@foreach(\App\Models\SocialLink::PLATFORMS as $key => $label)
<div class="form-group"><label for="social_{{ $key }}">{{ $label }}</label>
<input type="url" id="social_{{ $key }}" name="{{ $key }}" value="{{ old($key, $links[$key] ?? '') }}" class="form-control {{ $errors->has($key) ? 'is-invalid' : '' }}" placeholder="https://..." maxlength="2048">
@error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
@endforeach
<button class="btn btn-primary" type="submit">Save social links</button>
</form></div></div></div>
@endsection

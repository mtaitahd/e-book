@extends('layouts.admin.app')

@section('title', 'Preview: ' . $chapter->title)

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $chapter->title }}</h1>
            <p class="text-muted small mb-0">
                {{ $book->title }} · Chapter {{ $chapter->position }} · {{ $chapter->slug }}
            </p>
        </div>

        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary"
               href="{{ route('admin.books.chapters.edit', [$book, $chapter]) }}">Edit</a>
            <a class="btn btn-outline-secondary"
               href="{{ route('admin.books.chapters.index', $book) }}">All chapters</a>
        </div>
    </div>

    <div class="alert alert-info small">
        This is a preview of the chapter as the reader receives it. Anything the
        sanitiser strips is already gone here, so what you see is what a customer gets.
    </div>

    <div class="card">
        <div class="card-body">
            {{-- Unescaped on purpose: this is the sanitiser's allowlist output,
                 the same string the authorized chapter endpoint returns. --}}
            <div class="orp-content orp-preview">{!! $html !!}</div>
        </div>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/storefront/css/online-reader.css') }}">
    <style>
        .orp-preview {
            max-width: 46rem;
            margin: 0 auto;
            font-size: 1.0625rem;
            line-height: 1.75;
        }
    </style>
@endpush

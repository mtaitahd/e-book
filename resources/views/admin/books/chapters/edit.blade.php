@extends('layouts.admin.app')

@section('title', 'Edit Chapter')
@section('heading', 'Edit Chapter')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">{{ $book->title }}</h6>
                <a href="{{ route('admin.books.chapters.index', $book) }}" class="btn btn-sm btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> All chapters
                </a>
            </div>
            <div class="card-body">
                @include('admin.books.chapters._form_page', ['book' => $book, 'chapter' => $chapter])
            </div>
        </div>
    </div>
@endsection

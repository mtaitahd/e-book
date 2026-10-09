@extends('layouts.admin.app')

@section('title', 'Add Chapter')
@section('heading', 'Add Chapter')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ $book->title }}</h6>
            </div>
            <div class="card-body">
                @include('admin.books.chapters._form_page', ['book' => $book, 'chapter' => $chapter])
            </div>
        </div>
    </div>
@endsection

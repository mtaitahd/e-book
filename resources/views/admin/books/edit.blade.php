@extends('layouts.admin.app')

@section('title', 'Edit Book')
@section('heading', 'Edit Book')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Book details</h6>
            </div>
            <div class="card-body">
                @include('admin.books._form_page', ['book' => $book, 'authors' => $authors, 'categories' => $categories, 'stats' => $stats ?? ['owners' => 0]])
            </div>
        </div>
    </div>
@endsection

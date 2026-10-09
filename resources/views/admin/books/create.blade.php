@extends('layouts.admin.app')

@section('title', 'Add Book')
@section('heading', 'Add Book')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Book details</h6>
            </div>
            <div class="card-body">
                @include('admin.books._form_page', ['book' => null, 'authors' => $authors, 'categories' => $categories, 'stats' => $stats ?? ['owners' => 0]])
            </div>
        </div>
    </div>
@endsection

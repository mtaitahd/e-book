@extends('layouts.admin.app')

@section('title', 'Add Category')
@section('heading', 'Add Category')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Category details</h6>
            </div>
            <div class="card-body">
                @include('admin.categories._form_page', ['category' => null])
            </div>
        </div>
    </div>
@endsection

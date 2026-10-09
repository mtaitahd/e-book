@extends('layouts.admin.app')

@section('title', 'Add Author')
@section('heading', 'Add Author')

@section('content')
    <div class="container-fluid">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Author details</h6>
            </div>
            <div class="card-body">
                @include('admin.authors._form_page', ['author' => null])
            </div>
        </div>
    </div>
@endsection

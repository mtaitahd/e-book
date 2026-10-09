@extends('layouts.admin.app')

@section('title', 'Manage Categories')
@section('heading', 'Categories')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                {{ $categories->total() }} categor{{ $categories->total() === 1 ? 'y' : 'ies' }} found
            </div>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-icon-split"
               data-modal-form="{{ route('admin.categories.create') }}" data-modal-title="Add Category">
                <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
                <span class="text">Add Category</span>
            </a>
        </div>

        @if($categories->isEmpty())
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-tags fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-1 text-gray-800">No categories yet.</p>
                    <p class="small text-muted mb-3">Organise your catalogue into browsable sections.</p>
                    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm"
                               data-modal-form="{{ route('admin.categories.create') }}" data-modal-title="Add Category">
                        <i class="fas fa-plus mr-1"></i> Add Category
                    </a>
                </div>
            </div>
        @else
            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Description</th>
                                    <th scope="col" style="width: 100px;">Books</th>
                                    <th scope="col" style="width: 110px;">Status</th>
                                    <th scope="col" style="width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $category)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.categories.edit', $category) }}"
                                   data-modal-form="{{ route('admin.categories.edit', $category) }}"
                                   data-modal-title="Edit category">{{ $category->name }}</a>
                                            <span class="d-block small text-muted">{{ $category->slug }}</span>
                                        </td>
                                        <td class="text-truncate" style="max-width: 320px;">{{ $category->description ?: '—' }}</td>
                                        <td>{{ $category->books_count }}</td>
                                        <td>
                                            @if($category->isActive())
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-primary btn-sm" title="Edit"
                                                      data-modal-form="{{ route('admin.categories.edit', $category) }}"
                                                      data-modal-title="Edit category">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($category->isActive())
                                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                                      class="d-inline" onsubmit="return confirm('Deactivate this category? Book relationships are preserved.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-warning btn-sm" title="Archive">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.categories.restore', $category) }}"
                                                      class="d-inline" onsubmit="return confirm('Restore this category so it appears in the catalogue again?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-success btn-sm" title="Restore">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.categories.force-destroy', $category) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Permanently delete this category? This cannot be undone and is blocked while books still link to it.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete permanently"
                                                        @if($category->books_count > 0) disabled @endif>
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    {{ $categories->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
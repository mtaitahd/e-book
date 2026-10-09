@extends('layouts.admin.app')

@section('title', 'Manage Authors')
@section('heading', 'Authors')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                {{ $authors->total() }} author{{ $authors->total() === 1 ? '' : 's' }} found
            </div>
            <a href="{{ route('admin.authors.create') }}" class="btn btn-primary btn-icon-split"
               data-modal-form="{{ route('admin.authors.create') }}" data-modal-title="Add Author">
                <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
                <span class="text">Add Author</span>
            </a>
        </div>

        @if($authors->isEmpty())
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <i class="fas fa-pen-nib fa-3x text-gray-300 mb-3"></i>
                    <p class="mb-1 text-gray-800">No authors yet.</p>
                    <p class="small text-muted mb-3">Add the people who write your e-books.</p>
                    <a href="{{ route('admin.authors.create') }}" class="btn btn-primary btn-sm"
                               data-modal-form="{{ route('admin.authors.create') }}" data-modal-title="Add Author">
                        <i class="fas fa-plus mr-1"></i> Add Author
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
                                    <th scope="col">Bio</th>
                                    <th scope="col" style="width: 100px;">Books</th>
                                    <th scope="col" style="width: 110px;">Status</th>
                                    <th scope="col" style="width: 220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($authors as $author)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.authors.edit', $author) }}"
                                   data-modal-form="{{ route('admin.authors.edit', $author) }}"
                                   data-modal-title="Edit author">{{ $author->name }}</a>
                                            <span class="d-block small text-muted">{{ $author->slug }}</span>
                                        </td>
                                        <td class="text-truncate" style="max-width: 320px;">{{ $author->bio ?: '—' }}</td>
                                        <td>{{ $author->books_count }}</td>
                                        <td>
                                            @if($author->isActive())
                                                <span class="badge badge-success">Active</span>
                                            @else
                                                <span class="badge badge-danger">Archived</span>
                                            @endif
                                        </td>
<td>
                                            <a href="{{ route('admin.authors.edit', $author) }}" class="btn btn-primary btn-sm" title="Edit"
                                                      data-modal-form="{{ route('admin.authors.edit', $author) }}"
                                                      data-modal-title="Edit author">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($author->isActive())
                                                <form method="POST" action="{{ route('admin.authors.destroy', $author) }}"
                                                      class="d-inline" onsubmit="return confirm('Archive this author? Existing book attributions are preserved.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-warning btn-sm" title="Archive">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('admin.authors.restore', $author) }}"
                                                      class="d-inline" onsubmit="return confirm('Restore this author so they appear in the catalogue again?');">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="btn btn-success btn-sm" title="Restore">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.authors.force-destroy', $author) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Permanently delete this author? This cannot be undone and is blocked while books still credit them.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete permanently"
                                                        @if($author->books_count > 0) disabled @endif>
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
                    {{ $authors->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
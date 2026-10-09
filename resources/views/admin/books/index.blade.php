@extends('layouts.admin.app')

@section('title', 'Manage Books')
@section('heading', 'Books')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div class="text-muted small">
                {{ $books->total() }} book{{ $books->total() === 1 ? '' : 's' }} found
            </div>
            <a href="{{ route('admin.books.create') }}" class="btn btn-primary btn-icon-split"
               data-modal-form="{{ route('admin.books.create') }}" data-modal-title="Add Book">
                <span class="icon text-white-50"><i class="fas fa-plus"></i></span>
                <span class="text">Add Book</span>
            </a>
        </div>

        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link {{ $currentStatus === null ? 'active' : '' }}" href="{{ route('admin.books.index') }}">All</a>
            </li>
            @foreach(['draft', 'published', 'archived'] as $status)
                <li class="nav-item">
                    <a class="nav-link {{ $currentStatus === $status ? 'active' : '' }}"
                       href="{{ route('admin.books.index', ['status' => $status]) }}">
                        {{ ucfirst($status) }}
                    </a>
                </li>
            @endforeach
        </ul>

        @if($books->isEmpty())
            <div class="card shadow mb-4">
                <div class="card-body text-center py-5">
                    <div class="text-center">
                        <i class="fas fa-book-open fa-3x text-gray-300 mb-3"></i>
                        <p class="mb-1 text-gray-800">No books found{{ $currentStatus ? ' with this status' : '' }}.</p>
                        <p class="small text-muted mb-3">Add your first book to start building the catalogue.</p>
                        <a href="{{ route('admin.books.create') }}" class="btn btn-primary btn-sm"
                               data-modal-form="{{ route('admin.books.create') }}" data-modal-title="Add Book">
                            <i class="fas fa-plus mr-1"></i> Add Book
                        </a>
                    </div>
                </div>
            </div>
        @else
            <div class="card shadow mb-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th scope="col" style="width: 70px;">Cover</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Authors</th>
                                    <th scope="col">Categories</th>
                                    <th scope="col" style="width: 110px;">Price</th>
                                    <th scope="col" style="width: 130px;">Format</th>
                                    <th scope="col" style="width: 110px;">Status</th>
                                    <th scope="col" style="width: 140px;">Published</th>
                                    <th scope="col" style="width: 180px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($books as $book)
                                    <tr>
                                        <td class="text-center">
                                            @if($book->cover_image)
                                                <img src="{{ asset('storage/' . $book->cover_image) }}"
                                                     alt="{{ $book->title }}" style="width:48px;height:64px;object-fit:cover;border-radius:4px;">
                                            @else
                                                <span class="text-gray-400"><i class="fas fa-book"></i></span>
                                            @endif
                                        </td>
                                        <td>
                                            <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
                                            <span class="d-block small text-muted">{{ $book->slug }}</span>
                                        </td>
                                        <td>
                                            @if($book->authors->isNotEmpty())
                                                {{ $book->authors->pluck('name')->implode(', ') }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @forelse($book->categories as $category)
                                                <span class="badge badge-secondary">{{ $category->name }}</span>
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td>{{ $book->price }}</td>
                                        <td>
                                            @if($book->isOnlineFormat())
                                                <a href="{{ route('admin.books.chapters.index', $book) }}"
                                                   class="badge badge-info" title="Manage chapters">Chapters</a>
                                            @endif
                                            <span class="badge badge-light border">{{ $book->formatLabel() }}</span>
                                            @if($book->isOnlineFormat() && $book->chapters_count === 0)
                                                <span class="d-block text-danger small mt-1">No chapters</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($book->isPublished())
                                                <span class="badge badge-success">Published</span>
                                            @elseif($book->status === 'archived')
                                                <span class="badge badge-danger">Archived</span>
                                            @else
                                                <span class="badge badge-warning">Draft</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $book->published_at?->format('M j, Y') ?: '—' }}
                                        </td>
                                        <td>
                                            <a href="{{ route('books.show', $book) }}" class="btn btn-info btn-sm" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="{{ route('admin.books.edit', $book) }}" class="btn btn-primary btn-sm" title="Edit"
                                                    data-modal-form="{{ route('admin.books.edit', $book) }}"
                                                    data-modal-title="Edit book">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @unless($book->isArchived())
                                                <form method="POST" action="{{ route('admin.books.destroy', $book) }}"
                                                      class="d-inline" onsubmit="return confirm('Archive this book? It will disappear from the public catalogue.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-warning btn-sm" title="Archive">
                                                        <i class="fas fa-archive"></i>
                                                    </button>
                                                </form>
                                            @endunless
                                            <form method="POST" action="{{ route('admin.books.force-destroy', $book) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Permanently delete this book? This cannot be undone and will remove its chapters, file and cover.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        title="@if($book->isArchived()) Delete permanently @else Delete permanently (not available for sold books) @endif" >
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
                    {{ $books->links() }}
                </div>
            </div>
        @endif
    </div>
@endsection
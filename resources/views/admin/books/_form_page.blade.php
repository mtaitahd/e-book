{{--
    The book form.

    Used by the standalone create/edit pages and, when the request is an XHR,
    injected straight into the shared admin modal. Because both paths render
    this one partial they can never drift apart.

    Expects: $book (Book|null), $authors, $categories.
    Optional: $modal (true when rendered into the popup).
--}}
@php
    $isEdit = $book && $book->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('admin.books.update', $book) : route('admin.books.store') }}"
      enctype="multipart/form-data"
      class="admin-modal-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @if($isEdit && ($stats['owners'] ?? 0) > 0)
        {{-- Ownership is permanent, so the admin can see the reach of a price
             change before making it. --}}
        <div class="alert alert-info py-2">
            <i class="fas fa-users mr-1"></i>
            {{ $stats['owners'] }} customer(s) already own this book. Changing the price does not affect them.
        </div>
    @endif

    @include('admin.books._form', ['book' => $book])

    <hr>

    <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary btn-icon-split">
            <span class="icon text-white-50"><i class="fas fa-save"></i></span>
            <span class="text">{{ $isEdit ? 'Update Book' : 'Create Book' }}</span>
        </button>

        <span>
            <a href="{{ route('admin.books.index') }}" class="btn btn-secondary admin-page-cancel">Cancel</a>
            <button type="button" class="btn btn-secondary admin-modal-cancel" data-dismiss="modal">Cancel</button>
        </span>
    </div>
</form>

@if($isEdit)
    {{-- Archiving is a destructive action, so it stays out of the popup and
         only appears on the standalone edit page. --}}
    @unless($modal ?? false)
        <div class="mt-4 border-top pt-3">
            <form method="POST" action="{{ route('admin.books.destroy', $book) }}"
                  onsubmit="return confirm('Archive this book? It will disappear from the public catalogue.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-archive mr-1"></i> Archive book
                </button>
                <span class="small text-muted ml-2">Archiving hides the book from the storefront. Books are never hard-deleted.</span>
            </form>
        </div>
    @endunless
@endif

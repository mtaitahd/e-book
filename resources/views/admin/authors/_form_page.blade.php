{{--
    The author form, shared by the standalone pages and the admin popup.

    Expects: $author (Author|null). Optional: $modal.
--}}
@php
    $isEdit = $author && $author->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('admin.authors.update', $author) : route('admin.authors.store') }}"
      class="admin-modal-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @include('admin.authors._form', ['author' => $author])

    <hr>

    <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary btn-icon-split">
            <span class="icon text-white-50"><i class="fas fa-save"></i></span>
            <span class="text">{{ $isEdit ? 'Update Author' : 'Create Author' }}</span>
        </button>

        <span>
            <a href="{{ route('admin.authors.index') }}" class="btn btn-secondary admin-page-cancel">Cancel</a>
            <button type="button" class="btn btn-secondary admin-modal-cancel" data-dismiss="modal">Cancel</button>
        </span>
    </div>
</form>

@if($isEdit && !($modal ?? false))
    <div class="mt-4 border-top pt-3">
        <form method="POST" action="{{ route('admin.authors.destroy', $author) }}"
              onsubmit="return confirm('Archive this author? Existing book attributions are preserved.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-archive mr-1"></i> Archive author
            </button>
            <span class="small text-muted ml-2">Authors are never hard-deleted, so book relationships always stay valid.</span>
        </form>
    </div>
@endif

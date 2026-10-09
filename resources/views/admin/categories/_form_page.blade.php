{{--
    The category form, shared by the standalone pages and the admin popup.

    Expects: $category (Category|null). Optional: $modal.
--}}
@php
    $isEdit = $category && $category->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
      class="admin-modal-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @include('admin.categories._form', ['category' => $category])

    <hr>

    <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary btn-icon-split">
            <span class="icon text-white-50"><i class="fas fa-save"></i></span>
            <span class="text">{{ $isEdit ? 'Update Category' : 'Create Category' }}</span>
        </button>

        <span>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-secondary admin-page-cancel">Cancel</a>
            <button type="button" class="btn btn-secondary admin-modal-cancel" data-dismiss="modal">Cancel</button>
        </span>
    </div>
</form>

@if($isEdit && !($modal ?? false))
    <div class="mt-4 border-top pt-3">
        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
              onsubmit="return confirm('Deactivate this category? Book relationships are preserved.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-archive mr-1"></i> Deactivate category
            </button>
            <span class="small text-muted ml-2">Categories are never hard-deleted, so book relationships always stay valid.</span>
        </form>
    </div>
@endif

{{--
    The chapter form, shared by the standalone pages and the admin popup.

    Expects: $book (Book), $chapter (BookChapter - an unsaved model when
    creating, so `$chapter->exists` decides the action and the label).
    Optional: $modal.
--}}
@php
    $isEdit = $chapter->exists;
@endphp

<form method="POST"
      action="{{ $isEdit ? route('admin.books.chapters.update', [$book, $chapter]) : route('admin.books.chapters.store', $book) }}"
      class="admin-modal-form">
    @csrf
    @if($isEdit) @method('PUT') @endif

    @include('admin.books.chapters._form', ['book' => $book, 'chapter' => $chapter])

    <hr>

    <div class="d-flex justify-content-between align-items-center">
        <button type="submit" class="btn btn-primary btn-icon-split">
            <span class="icon text-white-50"><i class="fas fa-save"></i></span>
            <span class="text">{{ $isEdit ? 'Update Chapter' : 'Create Chapter' }}</span>
        </button>

        <span>
            <a href="{{ route('admin.books.chapters.index', $book) }}" class="btn btn-secondary admin-page-cancel">Cancel</a>
            <button type="button" class="btn btn-secondary admin-modal-cancel" data-dismiss="modal">Cancel</button>
        </span>
    </div>
</form>

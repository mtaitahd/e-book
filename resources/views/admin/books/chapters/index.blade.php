@extends('layouts.admin.app')

@section('title', 'Chapters')
@section('heading', 'Chapters')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div>
                <h6 class="m-0 font-weight-bold text-primary">{{ $book->title }}</h6>
                <small class="text-muted">
                    {{ $chapters->count() }} chapter(s) &mdash; format: {{ $book->formatLabel() }}
                </small>
            </div>
            <div>
                <a href="{{ route('admin.books.chapters.create', $book) }}" class="btn btn-primary btn-sm"
                   data-modal-form="{{ route('admin.books.chapters.create', $book) }}"
                   data-modal-title="Add chapter to {{ $book->title }}" data-modal-size="modal-xl">
                    <i class="fas fa-plus mr-1"></i> Add chapter
                </a>
                <a href="{{ route('admin.books.edit', $book) }}" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Back to book
                </a>
            </div>
        </div>

        @if($chapters->isEmpty())
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                    <p class="mb-1 font-weight-bold">No chapters yet.</p>
                    <p class="text-muted small mb-3">
                        An online book needs at least one chapter before it can be published.
                    </p>
                    <a href="{{ route('admin.books.chapters.create', $book) }}" class="btn btn-primary btn-sm"
                       data-modal-form="{{ route('admin.books.chapters.create', $book) }}"
                       data-modal-title="Add chapter to {{ $book->title }}" data-modal-size="modal-xl">
                        Write the first chapter
                    </a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('admin.books.chapters.reorder', $book) }}" id="chapterOrderForm">
                @csrf
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Reading order</h6>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-save mr-1"></i> Save order
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:70px;">Order</th>
                                        <th>Chapter</th>
                                        <th style="width:120px;">Access</th>
                                        <th style="width:120px;">Size</th>
                                        <th style="width:190px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="chapterRows">
                                    @foreach($chapters as $chapter)
                                        <tr data-chapter-row data-chapter-id="{{ $chapter->id }}">
                                            <td>
                                                <input type="hidden" name="order[]" value="{{ $chapter->id }}">
                                                <div class="d-flex align-items-center">
                                                    <span class="js-position badge badge-light border mr-2">{{ $loop->iteration }}</span>
                                                    <div class="btn-group btn-group-sm" role="group" aria-label="Reorder">
                                                        <button type="button" class="btn btn-outline-secondary js-move" data-direction="up"
                                                                aria-label="Move chapter up">&uarr;</button>
                                                        <button type="button" class="btn btn-outline-secondary js-move" data-direction="down"
                                                                aria-label="Move chapter down">&darr;</button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>{{ $chapter->title }}</strong>
                                                <div class="small text-muted">{{ $chapter->slug }}</div>
                                            </td>
                                            <td>
                                                @if($chapter->is_free)
                                                    <span class="badge badge-success">Free</span>
                                                @else
                                                    <span class="badge badge-secondary">Purchase</span>
                                                @endif
                                            </td>
                                            <td class="small text-muted">
                                                {{ number_format(str_word_count(strip_tags($chapter->content))) }} words
                                            </td>
                                            <td class="text-right">
                                                <a href="{{ route('admin.books.chapters.edit', [$book, $chapter]) }}"
                                                   class="btn btn-sm btn-primary"
                                                   data-modal-form="{{ route('admin.books.chapters.edit', [$book, $chapter]) }}"
                                                   data-modal-title="Edit chapter" data-modal-size="modal-xl">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger js-delete"
                                                        data-title="{{ $chapter->title }}"
                                                        data-token="{{ csrf_token() }}"
                                                        data-action="{{ route('admin.books.chapters.destroy', [$book, $chapter]) }}">
                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        @endif
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var rows = Array.prototype.slice.call(document.querySelectorAll('#chapterRows [data-chapter-row]'));

            rows.forEach(function (row) {
                row.querySelectorAll('.js-move').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var direction = button.getAttribute('data-direction');
                        var sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;

                        if (! sibling) { return; }

                        if (direction === 'up') {
                            row.parentNode.insertBefore(row, sibling);
                        } else {
                            row.parentNode.insertBefore(sibling, row);
                        }

                        renumber();
                    });
                });
            });

            document.querySelectorAll('.js-delete').forEach(function (button) {
                button.addEventListener('click', function () {
                    var title = button.getAttribute('data-title');
                    if (! window.confirm('Delete the chapter "' + title + '"? This cannot be undone.')) {
                        return;
                    }

                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = button.getAttribute('data-action');

                    var token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = button.getAttribute('data-token');
                    form.appendChild(token);

                    var method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(method);

                    document.body.appendChild(form);
                    form.submit();
                });
            });

            function renumber() {
                document.querySelectorAll('#chapterRows [data-chapter-row]').forEach(function (row, index) {
                    var badge = row.querySelector('.js-position');
                    if (badge) { badge.textContent = String(index + 1); }
                });
            }
        })();
    </script>
@endpush

@php
    /** @var \App\Models\Book|null $book */
    $book = $book ?? null;
    $isEdit = $book && $book->exists;
    $selectedAuthors = old('author_ids', $book?->authors->pluck('id')->all() ?? []);
    $selectedCategories = old('category_ids', $book?->categories->pluck('id')->all() ?? []);
    $statusValue = old('status', $book?->status ?? 'draft');
    $fileTypeValue = old('file_type', $book?->file_type ?? 'pdf');
    $pricingTypeValue = old('pricing_type', $book?->pricing_type ?? \App\Models\Book::PRICING_PAID);

    // The book type drives this whole form: it decides which fields exist
    // server side too (see BookRequest), so the two can never disagree.
    $typeValue = old('type', $book?->type() ?? \App\Models\Book::TYPE_PDF);
    if (! in_array($typeValue, \App\Models\Book::TYPES, true)) {
        $typeValue = \App\Models\Book::TYPE_PDF;
    }
    $originalType = $book?->type();

    // Legacy dual-format books keep their chapter management even when the
    // stored type reads as PDF, so an existing book never loses its editor.
    $formatValue = old('book_format', $book?->book_format ?? \App\Models\Book::FORMAT_PDF);
    $formatAllowsChapters = in_array($formatValue, [
        \App\Models\Book::FORMAT_ONLINE,
        \App\Models\Book::FORMAT_BOTH,
    ], true);
    $showChapters = $typeValue === \App\Models\Book::TYPE_EBOOK || $formatAllowsChapters;

    $chapterCount = $isEdit ? $book->chapters()->count() : 0;
@endphp

<style>
    .book-type-options { display: flex; gap: .75rem; flex-wrap: wrap; }
    .book-type-option {
        position: relative; flex: 1 1 12rem; display: block; margin: 0;
        border: 1px solid #d1d5db; border-radius: .5rem; padding: .7rem .9rem;
        background: #fff; cursor: pointer; transition: border-color .15s ease, box-shadow .15s ease;
    }
    .book-type-option:hover { border-color: #9ca3af; }
    .book-type-option input { position: absolute; opacity: 0; pointer-events: none; }
    .book-type-option .bt-title { display: block; font-weight: 600; color: #1f2937; }
    .book-type-option .bt-desc { display: block; font-size: .78rem; color: #6b7280; line-height: 1.35; }
    .book-type-option.is-checked {
        border-color: #1a56db; box-shadow: 0 0 0 2px rgba(26, 86, 219, .18);
    }
    .book-type-option input:checked ~ .bt-title { color: #1a56db; }
    .book-type-option:focus-within { outline: 2px solid #1a56db; outline-offset: 1px; }
</style>

{{-- ---------------------------------------------------------------
     Book type: the choice the rest of the workflow hangs from.
     --------------------------------------------------------------- --}}
<div class="form-group">
    <label class="d-block">Book Type <span class="text-danger">*</span></label>

    <div class="book-type-options" role="radiogroup" aria-label="Book type">
        <label class="book-type-option" for="type_ebook">
            <input type="radio" name="type" id="type_ebook" value="{{ \App\Models\Book::TYPE_EBOOK }}"
                   data-book-type-choice {{ $typeValue === \App\Models\Book::TYPE_EBOOK ? 'checked' : '' }}>
            <span class="bt-title"><i class="fas fa-book-open mr-1"></i>E-Book</span>
            <span class="bt-desc">Chapter by chapter content, read in the 3D book reader. No file upload.</span>
        </label>

        <label class="book-type-option" for="type_pdf">
            <input type="radio" name="type" id="type_pdf" value="{{ \App\Models\Book::TYPE_PDF }}"
                   data-book-type-choice {{ $typeValue === \App\Models\Book::TYPE_PDF ? 'checked' : '' }}>
            <span class="bt-title"><i class="fas fa-file-pdf mr-1"></i>PDF</span>
            <span class="bt-desc">One complete PDF, read in the secure PDF reader and downloaded after purchase.</span>
        </label>
    </div>

    @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

    @if($isEdit)
        {{-- Changing the type of an existing book is a deliberate act. The
             server refuses it outright once the book is owned, and otherwise
             insists on this confirmation. --}}
        <div class="custom-control custom-checkbox mt-2" data-book-type-confirm
             data-book-type-original="{{ $originalType }}" hidden>
            <input type="checkbox" class="custom-control-input" id="confirm_type_change"
                   name="confirm_type_change" value="1"
                   {{ old('confirm_type_change') ? 'checked' : '' }}>
            <label class="custom-control-label" for="confirm_type_change">
                I understand this changes the book's type. Chapters and PDF files already stored are kept.
            </label>
        </div>
        @error('confirm_type_change')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @endif
</div>

{{-- Per-type guidance, shown before the fields it talks about. --}}
<div class="alert alert-info py-2" data-book-type-block="ebook" {{ $typeValue !== \App\Models\Book::TYPE_EBOOK ? 'hidden' : '' }}>
    <i class="fas fa-info-circle mr-1"></i>
    This book will be created chapter by chapter after saving.
</div>

<div class="alert alert-info py-2" data-book-type-block="pdf" {{ $typeValue !== \App\Models\Book::TYPE_PDF ? 'hidden' : '' }}>
    <i class="fas fa-info-circle mr-1"></i>
    Upload the complete PDF of this book. The PDF will be used for online reading and download according to purchase permissions.
</div>

<div class="form-group">
    <label for="title">Title <span class="text-danger">*</span></label>
    <input type="text" name="title" id="title" class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
           value="{{ old('title', $book?->title) }}" required maxlength="255">
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea name="description" id="description" rows="6"
              class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}"
              maxlength="10000">{{ old('description', $book?->description) }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label class="d-block">Availability <span class="text-danger">*</span></label>

            {{-- Radios, so "free" is an explicit choice and a paid book cannot
                 be created with an accidentally empty price box. The name is
                 pricing_type, matching the column and the validation key. --}}
            <div class="custom-control custom-radio mb-1">
                <input type="radio" class="custom-control-input" id="pricing_type_free"
                       name="pricing_type" value="{{ \App\Models\Book::PRICING_FREE }}"
                       data-book-pricing-choice
                       {{ $pricingTypeValue === \App\Models\Book::PRICING_FREE ? 'checked' : '' }}>
                <label class="custom-control-label" for="pricing_type_free">
                    <i class="fas fa-gift mr-1 text-success"></i>Free
                </label>
            </div>

            <div class="custom-control custom-radio mb-1">
                <input type="radio" class="custom-control-input" id="pricing_type_paid"
                       name="pricing_type" value="{{ \App\Models\Book::PRICING_PAID }}"
                       data-book-pricing-choice
                       {{ $pricingTypeValue === \App\Models\Book::PRICING_PAID ? 'checked' : '' }}>
                <label class="custom-control-label" for="pricing_type_paid">
                    <i class="fas fa-tag mr-1 text-primary"></i>Paid
                </label>
            </div>

            <small class="form-text text-muted">
                Free books go straight into a customer's library. Paid books are sold through the cart.
            </small>
            @error('pricing_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-6" data-book-price-field>
        <div class="form-group">
            <label for="price">Price <span class="text-danger" data-book-price-required>*</span></label>
            <div class="input-group">
                <input type="number" name="price" id="price" step="0.01" min="0.01" max="9999999.99"
                       class="form-control {{ $errors->has('price') ? 'is-invalid' : '' }}"
                       value="{{ old('price', $pricingTypeValue === \App\Models\Book::PRICING_FREE ? '' : $book?->price) }}"
                       data-book-price-input>
                <div class="input-group-append">
                    <span class="input-group-text">{{ config('shop.currency') }}</span>
                </div>
            </div>
            <small class="form-text text-muted">The amount each customer pays for this book.</small>
            @error('price')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="publisher">Publisher</label>
            <input type="text" name="publisher" id="publisher" class="form-control {{ $errors->has('publisher') ? 'is-invalid' : '' }}"
                   value="{{ old('publisher', $book?->publisher) }}" maxlength="255">
            @error('publisher')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="published_at">Published date</label>
            <input type="date" name="published_at" id="published_at" class="form-control {{ $errors->has('published_at') ? 'is-invalid' : '' }}"
                   value="{{ old('published_at', $book?->published_at?->format('Y-m-d')) }}">
            @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="form-group">
    <label for="status">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-control {{ $errors->has('status') ? 'is-invalid' : '' }}">
        @foreach(['draft', 'published', 'archived'] as $status)
            <option value="{{ $status }}" {{ $statusValue === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
        @endforeach
    </select>
    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label class="d-block">Authors <span class="text-danger">*</span></label>
    <div class="row">
        @forelse($authors as $author)
            <div class="col-md-4">
                <div class="custom-control custom-checkbox mb-1">
                    <input type="checkbox" class="custom-control-input {{ $errors->has('author_ids') ? 'is-invalid' : '' }}"
                           name="author_ids[]" id="author_{{ $author->id }}" value="{{ $author->id }}"
                           {{ in_array($author->id, $selectedAuthors) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="author_{{ $author->id }}">
                        {{ $author->name }}
                        @unless($author->isActive())<span class="badge badge-danger">archived</span>@endunless
                    </label>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">
                No authors yet. <a href="{{ route('admin.authors.create') }}">Create an author</a> first.
            </div>
        @endforelse
    </div>
    @error('author_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label class="d-block">Categories</label>
    <div class="row">
        @forelse($categories as $category)
            <div class="col-md-4">
                <div class="custom-control custom-checkbox mb-1">
                    <input type="checkbox" class="custom-control-input {{ $errors->has('category_ids') ? 'is-invalid' : '' }}"
                           name="category_ids[]" id="category_{{ $category->id }}" value="{{ $category->id }}"
                           {{ in_array($category->id, $selectedCategories) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="category_{{ $category->id }}">
                        {{ $category->name }}
                        @unless($category->isActive())<span class="badge badge-danger">inactive</span>@endunless
                    </label>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">
                No categories yet. <a href="{{ route('admin.categories.create') }}">Create a category</a> first.
            </div>
        @endforelse
    </div>
    @error('category_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label for="cover_image">Cover image</label>
    <div class="custom-file">
        <input type="file" name="cover_image" id="cover_image"
               class="custom-file-input {{ $errors->has('cover_image') ? 'is-invalid' : '' }}"
               accept="image/jpeg,image/png,image/webp">
        <label class="custom-file-label" for="cover_image">JPG, PNG or WebP &mdash; max 2 MB</label>
    </div>
    @error('cover_image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    {{-- Filled in by assets/admin/js/cover-preview.js with a preview of the
         chosen file, plus its name and size, before anything is uploaded. --}}
    <div id="coverImageFeedback"></div>
    @if($book?->cover_image)
        <div class="mt-2">
            <img id="coverImageCurrent" src="{{ asset('storage/' . $book->cover_image) }}" alt="Current cover"
                 style="width:72px;height:96px;object-fit:cover;border-radius:4px;">
            <span id="coverImageCurrentLabel" class="small text-muted ml-2">Current cover. Upload a new file to replace it.</span>
        </div>
    @endif
</div>

{{-- ---------------------------------------------------------------
     PDF only: the file that becomes the whole book.
     --------------------------------------------------------------- --}}
<div data-book-type-block="pdf" {{ $typeValue !== \App\Models\Book::TYPE_PDF ? 'hidden' : '' }}>
    <hr>
    <h6 class="text-muted small text-uppercase mb-3"><i class="fas fa-file-pdf mr-1"></i>Book file</h6>

    <div class="form-group">
        <label for="ebook_file">
            {{ $isEdit && $book?->file_path ? 'Replace PDF' : 'PDF upload' }}
            {{ $isEdit && $book?->file_path ? '' : '<span class="text-danger">*</span>' }}
        </label>

        @if($isEdit && $book?->file_path)
            <div class="alert alert-success py-2 mb-2">
                <i class="fas fa-file-pdf mr-1"></i>
                Current PDF: <strong>{{ basename($book->file_path) }}</strong>
                <span class="small text-muted">(stored privately &mdash; leave empty to keep it)</span>
            </div>
        @elseif($isEdit)
            <div class="alert alert-warning py-2 mb-2">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                No PDF uploaded yet. This book cannot be read or downloaded until one is added.
            </div>
        @endif

        <div class="custom-file">
            <input type="file" name="ebook_file" id="ebook_file"
                   class="custom-file-input {{ $errors->has('ebook_file') ? 'is-invalid' : '' }}"
                   accept="application/pdf"
                   data-book-pdf-file
                   data-book-pdf-create="{{ $isEdit ? '0' : '1' }}"
                   data-book-pdf-existing="{{ ($isEdit && $book?->file_path) ? '1' : '0' }}"
                   {{ $isEdit ? '' : 'required' }}>
            <label class="custom-file-label" for="ebook_file">One complete PDF &mdash; max 10 MB, stored privately</label>
        </div>
        @error('ebook_file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <div class="form-group">
        <label for="file_type">File type</label>
        <select name="file_type" id="file_type" class="form-control {{ $errors->has('file_type') ? 'is-invalid' : '' }}">
            @foreach(['pdf', 'epub', 'mobi'] as $type)
                <option value="{{ $type }}" {{ $fileTypeValue === $type ? 'selected' : '' }}>{{ strtoupper($type) }}</option>
            @endforeach
        </select>
        <small class="form-text text-muted">PDF books always store a PDF.</small>
        @error('file_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

{{-- ---------------------------------------------------------------
     E-Book only: where its chapters are built and managed.
     --------------------------------------------------------------- --}}
@if($showChapters)
    <div data-book-type-block="ebook" {{ $typeValue !== \App\Models\Book::TYPE_EBOOK ? 'hidden' : '' }}>
        <hr>
        <h6 class="text-muted small text-uppercase mb-3"><i class="fas fa-list mr-1"></i>Chapters</h6>

        <div class="form-group">
            <p class="small text-muted mb-2">
                This book is read chapter by chapter, not from a file.
                @if($isEdit && $chapterCount > 0)
                    It currently has <strong>{{ $chapterCount }}</strong> chapter(s).
                @elseif($isEdit)
                    <span class="text-danger font-weight-bold">It has no chapters yet and cannot be published.</span>
                @else
                    You will add them right after saving.
                @endif
            </p>

            @if($isEdit)
                <a href="{{ route('admin.books.chapters.index', $book) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-list mr-1"></i> Manage Chapters
                </a>
            @endif
        </div>
    </div>
@endif

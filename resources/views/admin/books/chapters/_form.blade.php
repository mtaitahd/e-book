@php
    /** @var \App\Models\Book $book */
    /** @var \App\Models\BookChapter $chapter */
    $isEdit = $chapter->exists;
@endphp

<div class="form-group">
    <label for="title">Chapter title <span class="text-danger">*</span></label>
    <input type="text" name="title" id="title" maxlength="255"
           class="form-control {{ $errors->has('title') ? 'is-invalid' : '' }}"
           value="{{ old('title', $chapter->title) }}" required>
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="slug">URL slug</label>
            <input type="text" name="slug" id="slug" maxlength="255"
                   class="form-control {{ $errors->has('slug') ? 'is-invalid' : '' }}"
                   value="{{ old('slug', $chapter->slug) }}"
                   placeholder="Generated from the title when left blank">
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="position">Position</label>
            <input type="number" name="position" id="position" min="0" max="100000"
                   class="form-control {{ $errors->has('position') ? 'is-invalid' : '' }}"
                   value="{{ old('position', $chapter->position) }}">
            @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group mt-md-4">
            <div class="custom-control custom-checkbox">
                <input type="hidden" name="is_free" value="0">
                <input type="checkbox" name="is_free" id="is_free" class="custom-control-input" value="1"
                       {{ old('is_free', $chapter->is_free ? 1 : 0) ? 'checked' : '' }}>
                <label class="custom-control-label" for="is_free">Free sample</label>
            </div>
        </div>
    </div>
</div>

<small class="form-text text-muted mb-2">
    A <strong>free sample</strong> chapter can be read by anyone from the book page, without buying the book.
    Leave it unticked to require a purchase.
</small>

<div class="form-group">
    <label for="content">Chapter content <span class="text-danger">*</span></label>

    <div class="btn-toolbar btn-toolbar-chapter mb-2" role="toolbar" aria-label="Formatting">
        <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
            <label class="btn btn-outline-secondary" data-chapter-command="formatBlock" data-chapter-value="p">Body</label>
            <label class="btn btn-outline-secondary" data-chapter-command="formatBlock" data-chapter-value="h2">H2</label>
            <label class="btn btn-outline-secondary" data-chapter-command="formatBlock" data-chapter-value="h3">H3</label>
            <label class="btn btn-outline-secondary" data-chapter-command="formatBlock" data-chapter-value="blockquote">Quote</label>
        </div>
        <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
            <label class="btn btn-outline-secondary" data-chapter-command="bold"><b>B</b></label>
            <label class="btn btn-outline-secondary" data-chapter-command="italic"><i>I</i></label>
        </div>
        <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
            <label class="btn btn-outline-secondary" data-chapter-command="insertUnorderedList">&bull; List</label>
            <label class="btn btn-outline-secondary" data-chapter-command="insertOrderedList">1. List</label>
        </div>
        <div class="btn-group btn-group-sm btn-group-toggle" data-toggle="buttons">
            <label class="btn btn-outline-secondary" data-chapter-command="createLink">Link</label>
        </div>
    </div>

    <textarea name="content" id="content" rows="18"
              class="form-control code-editor {{ $errors->has('content') ? 'is-invalid' : '' }}"
              required>{{ old('content', $chapter->content) }}</textarea>

    <small class="form-text text-muted">
        Paste the chapter text or HTML. Formatting is limited to a safe list of tags
        (headings, paragraphs, lists, quotes, links, images and tables); scripts, embedded
        media, inline styles and unknown markup are removed automatically when you save.
    </small>
    @error('content')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>

@php
    /** @var \App\Models\Author|null $author */
    $author = $author ?? null;
@endphp

<div class="form-group">
    <label for="name">Name <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
           value="{{ old('name', $author?->name) }}" required maxlength="255">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label for="bio">Bio</label>
    <textarea name="bio" id="bio" rows="5" class="form-control {{ $errors->has('bio') ? 'is-invalid' : '' }}"
              maxlength="5000">{{ old('bio', $author?->bio) }}</textarea>
    @error('bio')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
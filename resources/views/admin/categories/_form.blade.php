@php
    /** @var \App\Models\Category|null $category */
    $category = $category ?? null;
    $statusValue = old('status', $category?->status ?? 'active');
@endphp

<div class="form-group">
    <label for="name">Name <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
           value="{{ old('name', $category?->name) }}" required maxlength="100">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label for="description">Description</label>
    <textarea name="description" id="description" rows="4"
              class="form-control {{ $errors->has('description') ? 'is-invalid' : '' }}"
              maxlength="5000">{{ old('description', $category?->description) }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-group">
    <label for="status">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-control {{ $errors->has('status') ? 'is-invalid' : '' }}">
        <option value="active" {{ $statusValue === 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ $statusValue === 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>
    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
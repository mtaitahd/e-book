@extends('layouts.admin.app')

@section('title', 'My Profile')
@section('heading', 'My Profile')

@section('content')
    <div class="container-fluid">
        <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Profile photo</h6>
                        </div>
                        <div class="card-body text-center">
                            <img id="avatar-preview"
                                 src="{{ $user->avatar ? asset('storage/' . $user->avatar) : asset('assets/e_book-removebg-preview.png') }}"
                                 alt="Profile photo"
                                 class="rounded-circle mb-3"
                                 style="width: 140px; height: 140px; object-fit: cover; border: 3px solid #eee;">

                            <div class="custom-file">
                                <input type="file" name="avatar" id="avatar"
                                       class="custom-file-input {{ $errors->has('avatar') ? 'is-invalid' : '' }}"
                                       accept="image/jpeg,image/png,image/webp">
                                <label class="custom-file-label text-left" for="avatar">Choose a photo…</label>
                            </div>
                            @error('avatar')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            <p class="small text-muted mt-2 mb-0">JPG, PNG or WebP — max 2 MB.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Account details</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="name">Username</label>
                                <input type="text" name="name" id="name"
                                       class="form-control {{ $errors->has('name') ? 'is-invalid' : '' }}"
                                       value="{{ old('name', $user->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email address</label>
                                <input type="email" name="email" id="email"
                                       class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                       value="{{ old('email', $user->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mb-0">
                                <label for="phone">Phone number <span class="text-muted small">(optional)</span></label>
                                <input type="text" name="phone" id="phone"
                                       class="form-control {{ $errors->has('phone') ? 'is-invalid' : '' }}"
                                       value="{{ old('phone', $user->phone) }}"
                                       placeholder="07XXXXXXXXX">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Change password</h6>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted">Leave the fields below empty to keep your current password.</p>

                            <div class="form-group">
                                <label for="current_password">Current password</label>
                                <input type="password" name="current_password" id="current_password"
                                       class="form-control {{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                                       autocomplete="current-password">
                                @error('current_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="password">New password</label>
                                    <input type="password" name="password" id="password"
                                           class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                           autocomplete="new-password">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="password_confirmation">Confirm new password</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation"
                                           class="form-control" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-right">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save fa-sm mr-1"></i> Save changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var input = document.getElementById('avatar');
            var preview = document.getElementById('avatar-preview');
            if (!input || !preview) return;

            input.addEventListener('change', function () {
                var label = input.nextElementSibling;
                if (input.files && input.files[0]) {
                    if (label) label.textContent = input.files[0].name;
                    var reader = new FileReader();
                    reader.onload = function (e) { preview.src = e.target.result; };
                    reader.readAsDataURL(input.files[0]);
                }
            });
        })();
    </script>
@endpush

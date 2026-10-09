@php
    $modalErrors = ($errors instanceof \Illuminate\Support\ViewErrorBag) ? $errors : new \Illuminate\Support\ViewErrorBag;
    $modalFieldErrors = [];
    foreach (['email', 'password'] as $modalField) {
        if ($modalErrors->has($modalField)) {
            $modalFieldErrors[$modalField] = collect($modalErrors->get($modalField))->all();
        }
    }
    // Registration failures fall back to the same page without JavaScript, where
    // the messages have to be rendered next to their fields.
    $registerFieldErrors = [];
    foreach (['name', 'email', 'password'] as $registerField) {
        if ($modalErrors->has($registerField)) {
            $registerFieldErrors[$registerField] = collect($modalErrors->get($registerField))->all();
        }
    }
    // Flashed by the guest redirect when a protected page (e.g. checkout) sent
    // the visitor here; the modal resumes it once the customer signs in.
    $authIntended = session('auth_intended');
    // The hidden _auth_view marker tells the no-JS fallback which form was
    // submitted, so the popup reopens on the view that failed validation
    // instead of always on sign-in.
    $modalView = old('_auth_view') === 'register' ? 'register' : 'login';
    $modalHasState = $modalView === 'register' || !empty(old('email')) || !empty($modalFieldErrors) || !empty($authIntended);
@endphp

<div
    class="ebs-auth-overlay"
    id="ebsAuthOverlay"
    hidden
    data-auth-auto="{{ $modalHasState ? '1' : '' }}"
    data-auth-view="{{ $modalView }}"
    data-auth-errors="{{ json_encode($modalFieldErrors) }}"
    data-auth-old="{{ json_encode(['email' => old('email')]) }}"
    data-auth-intended="{{ $authIntended }}"
>
    <div class="ebs-auth-modal" id="ebsAuthPanel" role="dialog" aria-modal="true" aria-labelledby="ebsAuthTitle">
        <button class="ebs-auth-modal__close" id="ebsAuthClose" type="button" aria-label="Close">&#10005;</button>

        <div class="ebs-auth-modal__head">
            <img class="ebs-auth-modal__logo" src="{{ asset('assets/e_book-removebg-preview.png') }}" alt="E-Book" width="88" height="64">
        </div>

        {{-- Shared by both views so the dialog always has a visible label. --}}
        <h2 class="ebs-auth-modal__title" id="ebsAuthTitle">Welcome Back</h2>
        <p class="ebs-auth-modal__subtitle" id="ebsAuthSubtitle">Sign in to your account to continue</p>

        {{-- Login --}}
        <div class="ebs-auth-view" id="ebsLoginView" data-title="Welcome Back" data-subtitle="Sign in to your account to continue">
            <form class="ebs-auth-form" id="ebsLoginForm" method="POST" action="{{ route('login.store') }}" novalidate>
                @csrf
                <div class="ebs-auth-field">
                    <label for="ebsLoginEmail">Email or Username</label>
                    <div class="ebs-auth-input">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>
                                <path d="m22 6-10 7L2 6"/>
                            </svg>
                        </span>
                        <input id="ebsLoginEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="you@example.com" required>
                    </div>
                    <p class="ebs-auth-error" data-error-for="email"></p>
                </div>
                <div class="ebs-auth-field">
                    <label for="ebsLoginPassword">Password</label>
                    <div class="ebs-auth-input ebs-auth-input--eye">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input id="ebsLoginPassword" name="password" type="password" autocomplete="current-password" placeholder="••••••••" required>
                        <button type="button" class="ebs-auth-eye" data-auth-eye="ebsLoginPassword" aria-label="Show password">
                            <svg class="ebs-auth-eye__open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="ebs-auth-eye__off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <p class="ebs-auth-error" data-error-for="password"></p>
                </div>
                <div class="ebs-auth-row">
                    <label class="ebs-auth-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="ebs-auth-forgot" data-auth-forgot>Forgot password?</a>
                </div>
                <button class="ebs-auth-submit" type="submit"><span class="ebs-auth-submit__label">Sign in</span></button>
            </form>

            <p class="ebs-auth-alt">Don't have an account? <a href="#" class="ebs-auth-switch" data-auth-switch="register">Create one</a></p>
        </div>

        {{-- Create account --}}
        <div class="ebs-auth-view" id="ebsRegisterView" data-title="Create Account" data-subtitle="Sign up to buy and read your e-books" hidden>
            <form class="ebs-auth-form" id="ebsRegisterForm" method="POST" action="{{ route('register.store') }}" novalidate>
                @csrf
                {{-- Marks this as the submitted form so the no-JS fallback can
                     reopen the popup on the right view. --}}
                <input type="hidden" name="_auth_view" value="register">
                <div class="ebs-auth-field">
                    <label for="ebsRegisterName">Full Name</label>
                    <div class="ebs-auth-input">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        <input id="ebsRegisterName" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="Jane Doe" maxlength="255" required>
                    </div>
                    <p class="ebs-auth-error" data-error-for="name">@foreach($registerFieldErrors['name'] ?? [] as $registerNameError){{ $registerNameError }}@endforeach</p>
                </div>
                <div class="ebs-auth-field">
                    <label for="ebsRegisterEmail">Email</label>
                    <div class="ebs-auth-input">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>
                                <path d="m22 6-10 7L2 6"/>
                            </svg>
                        </span>
                        <input id="ebsRegisterEmail" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" maxlength="255" required>
                    </div>
                    <p class="ebs-auth-error" data-error-for="email">@foreach($registerFieldErrors['email'] ?? [] as $registerEmailError){{ $registerEmailError }}@endforeach</p>
                </div>
                <div class="ebs-auth-field">
                    <label for="ebsRegisterPhone">Mobile Money Number <span class="ebs-auth-optional">(optional)</span></label>
                    <div class="ebs-auth-input">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </span>
                        <input id="ebsRegisterPhone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" placeholder="07XXXXXXXXX" maxlength="32" inputmode="tel">
                    </div>
                    <p class="ebs-auth-hint">Used to pay for your orders by Mobile Money. You can change it any time when you pay.</p>
                    <p class="ebs-auth-error" data-error-for="phone">@foreach($registerFieldErrors['phone'] ?? [] as $registerPhoneError){{ $registerPhoneError }}@endforeach</p>
                </div>
                <div class="ebs-auth-field">
                    <label for="ebsRegisterPassword">Password</label>
                    <div class="ebs-auth-input ebs-auth-input--eye">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input id="ebsRegisterPassword" name="password" type="password" autocomplete="new-password" placeholder="At least 8 characters" required>
                        <button type="button" class="ebs-auth-eye" data-auth-eye="ebsRegisterPassword" aria-label="Show password">
                            <svg class="ebs-auth-eye__open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="ebs-auth-eye__off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <p class="ebs-auth-error" data-error-for="password">@foreach($registerFieldErrors['password'] ?? [] as $registerPasswordError){{ $registerPasswordError }}@endforeach</p>
                </div>
                <div class="ebs-auth-field">
                    <label for="ebsRegisterPasswordConfirm">Confirm Password</label>
                    <div class="ebs-auth-input ebs-auth-input--eye">
                        <span class="ebs-auth-input__icon" aria-hidden="true">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </span>
                        <input id="ebsRegisterPasswordConfirm" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Repeat your password" required>
                        <button type="button" class="ebs-auth-eye" data-auth-eye="ebsRegisterPasswordConfirm" aria-label="Show password">
                            <svg class="ebs-auth-eye__open" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="ebs-auth-eye__off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/>
                                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/>
                                <path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <button class="ebs-auth-submit" type="submit"><span class="ebs-auth-submit__label">Create account</span></button>
            </form>

            <p class="ebs-auth-alt">Already have an account? <a href="#" class="ebs-auth-switch" data-auth-switch="login">Sign in</a></p>
        </div>
    </div>
</div>

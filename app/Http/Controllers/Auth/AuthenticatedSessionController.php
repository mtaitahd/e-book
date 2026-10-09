<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Message shown when the submitted email/password pair does not match a user.
     */
    public const INVALID_CREDENTIALS = 'The email or password you entered is incorrect.';

    /**
     * Message shown when the account exists but has been suspended.
     */
    public const INACTIVE_ACCOUNT = 'Your account is not active. Please contact support.';

    /**
     * Message shown when valid customer credentials are submitted to the
     * administrator-only login page.
     */
    public const NOT_ADMIN = 'These credentials do not belong to an administrator account.';

    /**
     * Show the administrator login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate a storefront customer. This is the endpoint the auth modal
     * posts to, so failures answer with JSON for SweetAlert when requested.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (! Auth::attempt($this->credentials($request), $request->boolean('remember'))) {
            return $this->rejectLogin($request, self::INVALID_CREDENTIALS);
        }

        return $this->completeLogin($request);
    }

    /**
     * Authenticate an administrator for the /login page. Valid customer
     * credentials are rejected here so the page stays admin-only.
     */
    public function adminStore(Request $request): RedirectResponse|JsonResponse
    {
        if (! Auth::attempt($this->credentials($request), $request->boolean('remember'))) {
            return $this->rejectLogin($request, self::INVALID_CREDENTIALS);
        }

        $this->logoutIfUnusable($request);

        if (Auth::check() && ! Auth::user()->isAdmin()) {
            $this->endSession($request);

            return $this->rejectLogin($request, self::NOT_ADMIN);
        }

        return $this->completeLogin($request);
    }

    /**
     * Validate the submitted credentials.
     *
     * @return array<string, mixed>
     */
    protected function credentials(Request $request): array
    {
        return $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);
    }

    /**
     * Drop the session when the authenticated user cannot sign in, leaving the
     * request unauthenticated so the caller can report why.
     */
    protected function logoutIfUnusable(Request $request): void
    {
        if (Auth::check() && ! Auth::user()->isActive()) {
            $this->endSession($request);
        }
    }

    /**
     * Invalidate the authenticated session and clear the CSRF token.
     */
    protected function endSession(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    /**
     * Finish a successful sign-in and send the user to the right home.
     */
    protected function completeLogin(Request $request): RedirectResponse|JsonResponse
    {
        $user = Auth::user();

        if (! $user->isActive()) {
            $this->endSession($request);

            return $this->rejectLogin($request, self::INACTIVE_ACCOUNT);
        }

        $request->session()->regenerate();

        $redirect = $user->isAdmin()
            ? route('admin.dashboard')
            : route('account.show');

        return redirect()->intended($redirect);
    }

    /**
     * Bounce a failed sign-in back to the form, or answer with JSON for the
     * storefront auth modal so it can raise a SweetAlert instead of reloading.
     */
    protected function rejectLogin(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'title' => 'Login Failed',
                'errors' => ['email' => [$message]],
            ], 422);
        }

        return back()
            ->withErrors(['email' => $message])
            ->with('alert_title', 'Login Failed')
            ->with('alert_ok', 'Try Again')
            ->onlyInput('email');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->endSession($request);

        return redirect()->route('home');
    }
}
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        ]);

        // Signed-in visitors never need the login page: admins go to the
        // dashboard, everyone else back to the storefront.
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->isAdmin()
            ? route('admin.dashboard')
            : route('home'));

        // Guests signing in from the storefront use the auth modal, not the
        // administrator login page. The callback receives the request after the
        // web group has started the session, so it can flash the protected URL
        // for the modal to resume. It must return a plain URL: the redirect
        // helper cannot handle a RedirectResponse here.
        //
        // Note: this cannot live in a middleware, because Authenticate
        // implements AuthenticatesRequests and is therefore sorted ahead of any
        // middleware that is not listed in $middlewarePriority.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin', 'admin/*')) {
                return route('login');
            }

            $request->session()->flash('auth_intended', $request->fullUrl());

            $adminPath = (string) parse_url(route('admin.dashboard'), PHP_URL_PATH);
            $previous = (string) url()->previous();
            $previousPath = (string) parse_url($previous, PHP_URL_PATH);

            // Never bounce a storefront guest at the admin area or back at the
            // protected URL itself; the cart is a safe storefront landing page.
            $unusable = ! $previous
                || $previous === $request->fullUrl()
                || str_starts_with($previousPath, $adminPath);

            return $unusable ? route('cart.show') : $previous;
        });

        // Abliner callbacks are signed with a shared secret (HMAC) instead of
        // a session cookie, so the webhook route is exempt from CSRF. Every
        // other route in the application keeps full CSRF protection.
        $middleware->validateCsrfTokens(except: [
            'webhooks/abliner',
        ]);

        // Cloudflare (and any TLS-terminating proxy in front of this app)
        // reaches PHP over plain HTTP and describes the original request in
        // X-Forwarded-Proto. Without trusting it, Laravel believes every
        // request arrived over http:// and asset()/route() then EMIT http://
        // URLs onto an HTTPS page, which the browser blocks as mixed content
        // — the page renders completely unstyled.
        //
        // '*' trusts the header on any peer. That is only safe because the
        // only thing listening on this app is the local tunnel; if this is
        // ever exposed directly, pin it to the proxy's address instead.
        $middleware->trustProxies(
            at: '*',
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PREFIX,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

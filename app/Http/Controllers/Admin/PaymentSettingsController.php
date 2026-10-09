<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Settings\PaymentSettingsService;
use App\Settings\PaymentSettingsWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Admin-only management of the Abliner payment configuration.
 *
 * The API key and the webhook signing secret are stored encrypted (Laravel's
 * `encrypted` cast, keyed by APP_KEY) in `payment_settings`. A null column
 * means "not set here", so the environment value is used instead and an
 * existing `.env`-only deployment keeps working untouched.
 *
 * All actions are CSRF protected and require an administrator. Secrets are
 * never written to the session, so a revealed value exists only in the direct
 * HTTP response and is never left lying around in the session store.
 */
class PaymentSettingsController extends Controller
{
    public function index(PaymentSettingsService $settings): View
    {
        return view('admin.settings.payments', [
            'status' => $settings->status(),
            'activity' => $settings->activity(),
            'webhook' => $settings->webhookPosture(),
        ]);
    }

    /**
     * Save the settings form.
     *
     * A blank credential input keeps the stored value; erasing one is the
     * separate, explicit clear action below.
     */
    public function update(Request $request, PaymentSettingsWriter $writer): RedirectResponse
    {
        $validated = $request->validate([
            // Left as strings: these are secrets typed by a human, and a
            // 'string' rule is what stops a 5000-character paste from becoming
            // a giant encrypted blob.
            'api_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'string', 'max:2048', 'url'],
            'enabled' => ['nullable', 'boolean'],
            'verify_on_webhook' => ['nullable', 'boolean'],
        ], [
            'webhook_url.url' => 'The webhook URL must be a full http or https address.',
        ]);

        $writer->update($validated, $request->user());

        return back()->with('success', 'Payment settings saved.');
    }

    /**
     * Forget one stored credential so the environment value takes effect again.
     */
    public function clear(Request $request, string $field, PaymentSettingsWriter $writer): RedirectResponse
    {
        abort_unless(
            in_array($field, PaymentSetting::SECRET_FIELDS, true),
            404
        );

        $writer->clearSecret($field, $request->user());

        return back()->with(
            'success',
            'The stored value was removed. The server environment value applies again, if one is set.'
        );
    }

    /**
     * Drop the entire saved row, returning every field to the environment.
     */
    public function reset(Request $request, PaymentSettingsWriter $writer): RedirectResponse
    {
        $writer->resetToEnvironment($request->user());

        return back()->with(
            'success',
            'Saved payment settings were removed. The server environment values apply again, if any are set.'
        );
    }

    /**
     * Show a stored credential once, for an administrator who needs to copy it
     * into the Abliner dashboard.
     *
     * Rendered directly rather than flashed to the session: sessions are stored
     * in the database here, and a secret sitting in one is a secret a later
     * request could pick up and echo.
     */
    public function reveal(
        Request $request,
        string $field,
        PaymentSettingsWriter $writer,
    ): Response|RedirectResponse {
        abort_unless(
            in_array($field, PaymentSetting::SECRET_FIELDS, true),
            404
        );

        // A GET must never render a secret: it could be triggered by a
        // prefetching link or a browser history entry.
        if ($request->isMethod('get')) {
            return back();
        }

        $value = $writer->reveal($field, $request->user());

        if ($value === null) {
            return back()->with(
                'error',
                'There is no stored value to show. Set one in the form above first.'
            );
        }

        // no-store so the value is not kept in a shared or back-button cache,
        // and no-referrer so a stray link cannot leak this URL.
        return response()->view('admin.settings.reveal', [
            'field' => $field,
            'value' => $value,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Verify reachability and credentials with a single read-only request.
     */
    public function testConnection(PaymentSettingsService $settings): RedirectResponse
    {
        $result = $settings->testConnection();

        return back()->with(
            $result->isSuccess() ? 'success' : 'error',
            $result->message
        )->with('connectionTest', $result->outcome);
    }
}

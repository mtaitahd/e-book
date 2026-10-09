@extends('layouts.admin.app')

@section('title', 'Payment Settings | E-Book Admin')
@section('heading', 'Payment Settings')

@section('content')
    <div class="container-fluid">

        {{-- One honest answer up front: can money move right now, and if not, why. --}}
        @if ($reason = $status->blockingReason())
            <div class="alert alert-warning" role="alert">
                <strong>Payments are not fully operational.</strong> {{ $reason }}
            </div>
        @else
            <div class="alert alert-success" role="alert">
                <strong>Abliner is enabled, configured and ready to accept payments.</strong>
                Webhook callbacks are verified, so completed payments will mark orders as paid automatically.
            </div>
        @endif

        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase text-gray-500 mb-2">Provider</div>
                        <div class="h5 mb-1">{{ $status->provider }}</div>
                        <span class="badge badge-{{ $status->enabled ? 'success' : 'secondary' }} mb-2">
                            {{ $status->enabled ? 'Enabled' : 'Switched off' }}
                        </span>
                        <div class="small text-gray-600">
                            @if ($status->isAvailable())
                                New payments can be started.
                            @else
                                New payments are refused.
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase text-gray-500 mb-2">Callback security</div>
                        <span class="badge badge-{{ $webhook->isHardened() ? 'success' : 'danger' }} mb-2">
                            {{ $webhook->isHardened() ? 'Verified' : 'Not verified' }}
                        </span>
                        <div class="small text-gray-600">
                            HMAC signature on every callback, {{ $webhook->toleranceLabel() }} replay window.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase text-gray-500 mb-2">Currency</div>
                        <div class="h5 mb-1">{{ $status->currency }}</div>
                        <div class="small text-gray-600">Minimum payment {{ $status->minimumAmountLabel() }}</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase text-gray-500 mb-2">Activity</div>
                        <div class="h5 mb-1">{{ number_format($activity->total) }}</div>
                        <div class="small text-gray-600">
                            {{ $activity->completed }} completed, {{ $activity->pending }} pending
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- The editable form. Blank credential inputs keep what is
                 already stored; erasing one is the explicit clear button. --}}
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Manage configuration</span>
                        <span class="small text-gray-500 ml-2">credentials are encrypted before they are stored</span>
                    </div>
                    <div class="card-body">
                        @if ($status->hasUndecryptableSecret)
                            <div class="alert alert-danger" role="alert">
                                A stored credential can no longer be decrypted, which usually means the
                                application key changed. Re-enter both credentials below.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.settings.payments.update') }}">
                            @csrf

                            @if ($errors->any())
                                <div class="alert alert-danger" role="alert">
                                    <ul class="mb-0 pl-3">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            <div class="form-group">
                                <label for="api_key">API key</label>
                                <input type="password" class="form-control" id="api_key" name="api_key"
                                       value="" autocomplete="off" spellcheck="false"
                                       placeholder="{{ $status->hasApiKey ? 'Leave blank to keep the current value' : 'Paste the Abliner API key (tsl_live_...)' }}">
                                <small class="form-text text-muted">
                                    @if ($status->hasApiKey)
                                        Currently {{ $status->apiKeySource === 'database' ? 'saved on this page' : 'set on the server' }}@if ($status->apiKeyHint !== '') ({{ $status->apiKeyHint }})@endif.
                                    @else
                                        Not set. Payments cannot be started until this is provided.
                                    @endif
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="webhook_secret">Webhook secret</label>
                                <input type="password" class="form-control" id="webhook_secret" name="webhook_secret"
                                       value="" autocomplete="off" spellcheck="false"
                                       placeholder="{{ $status->hasWebhookSecret ? 'Leave blank to keep the current value' : 'Paste the Abliner webhook secret (whsec_...)' }}">
                                <small class="form-text text-muted">
                                    @if ($status->hasWebhookSecret)
                                        Currently {{ $status->webhookSecretSource === 'database' ? 'saved on this page' : 'set on the server' }}@if ($status->webhookSecretHint !== '') ({{ $status->webhookSecretHint }})@endif.
                                    @else
                                        Not set. Without it, payments cannot be confirmed automatically.
                                    @endif
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="webhook_url">Webhook URL</label>
                                <input type="url" class="form-control" id="webhook_url" name="webhook_url"
                                       value="{{ old('webhook_url', $status->webhookUrl) }}"
                                       placeholder="https://example.test/webhooks/abliner">
                                <small class="form-text text-muted">
                                    Blank uses this app's own route:
                                    <code>{{ route('webhooks.abliner') }}</code>
                                </small>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="enabled" value="0">
                                        <input type="checkbox" class="custom-control-input" id="enabled" name="enabled"
                                               value="1" @checked(old('enabled', $status->enabled))>
                                        <label class="custom-control-label" for="enabled">Accept new payments</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Off refuses new payments only. Existing orders, paid orders and
                                        signed callbacks keep working.
                                    </small>
                                </div>

                                <div class="form-group col-md-6">
                                    <div class="custom-control custom-switch">
                                        <input type="hidden" name="verify_on_webhook" value="0">
                                        <input type="checkbox" class="custom-control-input" id="verify_on_webhook"
                                               name="verify_on_webhook" value="1"
                                               @checked(old('verify_on_webhook', $status->verifyOnWebhook))>
                                        <label class="custom-control-label" for="verify_on_webhook">Verify webhook signatures</label>
                                    </div>
                                    <small class="form-text text-muted">
                                        Keep this on. Turning it off accepts unverified callbacks that can mark
                                        any order as paid.
                                    </small>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Save settings</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Stored values</span>
                    </div>
                    <div class="card-body">
                        @if ($status->hasStoredRow)
                            <p class="small text-gray-600">
                                Last changed
                                {{ $status->updatedAt ? $status->updatedAt : 'recently' }}.
                            </p>

                            @foreach ($status->clearableFields() as $field)
                                @if ($status->hasApiKey || $status->hasWebhookSecret)
                                    <form method="POST" class="mb-2"
                                          action="{{ route('admin.settings.payments.clear', ['field' => $field]) }}"
                                          onsubmit="return confirm('Remove the stored {{ $field === 'api_key' ? 'API key' : 'webhook secret' }}? The server environment value will be used instead.');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Remove stored {{ $field === 'api_key' ? 'API key' : 'webhook secret' }}
                                        </button>
                                    </form>
                                @endif
                            @endforeach

                            <form method="POST" action="{{ route('admin.settings.payments.reset') }}"
                                  onsubmit="return confirm('Remove every saved payment setting and go back to the server environment?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">
                                    Reset everything to the server environment
                                </button>
                            </form>
                        @else
                            <p class="small text-muted mb-0">
                                Nothing is saved on this page. Every value is read from the server environment,
                                and saving the form above will store an encrypted copy in the database instead.
                            </p>
                        @endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Show a stored value</span>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            For copying into the Abliner dashboard. Displayed once, on its own page, and never
                            saved to your browser cache or to the session on this server.
                        </p>
                        @foreach (['api_key' => 'API key', 'webhook_secret' => 'Webhook secret'] as $field => $label)
                            <form method="POST" action="{{ route('admin.settings.payments.reveal', ['field' => $field]) }}" class="mb-2">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-primary">Show {{ strtolower($label) }}</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Configuration</span>
                        <span class="small text-gray-500 ml-2">where each value is coming from</span>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th scope="row" class="w-25">Provider enabled</th>
                                    <td>
                                        @if ($status->enabled)
                                            <span class="badge badge-success">Yes</span>
                                        @else
                                            <span class="badge badge-secondary">No</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">API base URL</th>
                                    <td><code>{{ $status->baseUrl }}</code></td>
                                </tr>
                                <tr>
                                    <th scope="row">Webhook URL</th>
                                    <td>
                                        @if ($status->webhookUrl)
                                            <code>{{ $status->webhookUrl }}</code>
                                            @if ($status->webhookUrlIsDerived())
                                                <span class="small text-gray-500 ml-2">derived from this app's route</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Not set</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">API key</th>
                                    <td>
                                        @if ($status->hasApiKey)
                                            <span class="badge badge-success">Configured</span>
                                        @else
                                            <span class="badge badge-danger">Not set</span>
                                        @endif
                                        <span class="small text-gray-500 ml-2">
                                            {{ $status->sourceLabel($status->apiKeySource) }}@if ($status->apiKeyHint !== ''), shown as {{ $status->apiKeyHint }}@endif
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Webhook secret</th>
                                    <td>
                                        @if ($status->hasWebhookSecret)
                                            <span class="badge badge-success">Configured</span>
                                        @else
                                            <span class="badge badge-danger">Not set</span>
                                        @endif
                                        <span class="small text-gray-500 ml-2">
                                            {{ $status->sourceLabel($status->webhookSecretSource) }}@if ($status->webhookSecretHint !== ''), shown as {{ $status->webhookSecretHint }}@endif
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Verify payment on webhook</th>
                                    <td>
                                        @if ($webhook->verifyOnWebhook)
                                            <span class="badge badge-success">Enabled</span>
                                        @else
                                            <span class="badge badge-danger">Disabled</span>
                                        @endif
                                        <span class="small text-gray-500 ml-2">confirms the transaction with Abliner before marking an order paid</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Callback endpoint</th>
                                    <td><code>{{ $webhook->endpoint }}</code></td>
                                </tr>
                                <tr>
                                    <th scope="row">Networks</th>
                                    <td>
                                        @foreach ($status->networks as $network)
                                            <span class="badge badge-light text-dark mr-1">{{ $network }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-5 mb-4">
                <div class="card mb-4">
                    <div class="card-header py-3"><span class="font-weight-bold">Connection test</span></div>
                    <div class="card-body">
                        <p class="small text-gray-600">
                            Sends one read-only request to the Abliner balance endpoint. It creates no payment,
                            moves no money and never leaves this server.
                        </p>

                        <form method="POST" action="{{ route('admin.settings.payments.connection') }}">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit">Test connection</button>
                        </form>

                        @if (session('connectionTest'))
                            <hr>
                            <div class="mb-1">
                                <span class="badge badge-{{ session('connectionTest') === 'success' ? 'success' : (in_array(session('connectionTest'), ['misconfigured', 'disabled'], true) ? 'secondary' : 'danger') }} mr-2">
                                    {{ ucfirst(session('connectionTest')) }}
                                </span>
                            </div>
                            <p class="small mb-0">{{ session('success') ?? session('error') }}</p>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header py-3"><span class="font-weight-bold">Payment activity</span></div>
                    <div class="card-body">
                        @if ($activity->isEmpty())
                            <p class="mb-0 text-gray-600">
                                No Abliner payments have been recorded yet.
                            </p>
                        @else
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row">Completed</th>
                                        <td class="text-right">{{ number_format($activity->completed) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Pending</th>
                                        <td class="text-right">{{ number_format($activity->pending) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Failed</th>
                                        <td class="text-right">{{ number_format($activity->failed) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Voided or expired</th>
                                        <td class="text-right">{{ number_format($activity->discarded) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Last payment</th>
                                        <td class="text-right">{{ $activity->lastPaymentLabel() }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Last completed</th>
                                        <td class="text-right">{{ $activity->lastCompletedLabel() }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        @endif
                        <p class="small text-gray-500 mt-3 mb-0">
                            Times shown in the reporting timezone ({{ $activity->timezone }}).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header py-3"><span class="font-weight-bold">Setting these values</span></div>
                    <div class="card-body">
                        <p class="small text-gray-600 mb-3">
                            The API key and webhook secret are deliberately not editable here. They stay in the
                            server environment so they are never written to the database or served as a file
                            inside the web root.
                        </p>
                        <ol class="small mb-3">
                            <li>Edit <code>.env</code> on the server.</li>
                            <li>Run <code>php artisan config:clear</code>.</li>
                            <li>Reload this page to confirm the status changes.</li>
                        </ol>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr><th scope="row" class="w-50"><code>ABLINER_ENABLED</code></th><td>Accept new payments</td></tr>
                                <tr><th scope="row"><code>ABLINER_API_KEY</code></th><td>Secret key</td></tr>
                                <tr><th scope="row"><code>ABLINER_WEBHOOK_SECRET</code></th><td>Secret callback key</td></tr>
                                <tr><th scope="row"><code>ABLINER_WEBHOOK_URL</code></th><td>Public callback URL</td></tr>
                                <tr><th scope="row"><code>ABLINER_BASE_URL</code></th><td>API root</td></tr>
                                <tr><th scope="row"><code>ABLINER_VERIFY_ON_WEBHOOK</code></th><td>Confirm on callback</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header py-3"><span class="font-weight-bold">Timezone</span></div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th scope="row" class="w-50">Application</th>
                                    <td><code>{{ config('app.timezone') }}</code></td>
                                </tr>
                                <tr>
                                    <th scope="row">Reporting</th>
                                    <td><code>{{ $activity->timezone }}</code></td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="small text-gray-600 mt-3 mb-0">
                            All recorded timestamps are stored and displayed in this one timezone, so the sales
                            report, the order list and this page always agree. Changing it would reinterpret
                            historical dates, so it is left alone.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

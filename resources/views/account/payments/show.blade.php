@extends('layouts.customer.app')

@section('title', 'Pay for Order ' . $order->order_number)

@section('content')
    <p class="back-link"><a href="{{ route('account.orders.show', $order) }}">&larr; Back to order {{ $order->order_number }}</a></p>

    <h1>Complete your payment</h1>
    <p class="lead">Order <strong>{{ $order->order_number }}</strong> — pay {{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($order->total), $order->currency) }}</p>

    @if(!$available)
        @if($minimumNotMet)
            <div class="flash error">
                <p>This order is below the {{ \App\Support\Money::formatWhole(\App\Services\Abliner\AblinerPaymentService::MINIMUM_AMOUNT) }} minimum payment and cannot be paid online yet.</p>
            </div>
        @elseif($maximumExceeded)
            <div class="flash error">
                <p>This order is above the {{ \App\Support\Money::formatWhole(\App\Services\Abliner\AblinerPaymentService::MAXIMUM_AMOUNT) }} maximum single payment and cannot be paid online yet.</p>
            </div>
        @else
            <div class="flash error">
                <p>This order cannot be paid online.</p>
            </div>
        @endif
    @else
        <div class="payment-panel">

            @if($notConfigured)
                <div class="flash note">
                    <h4>Payments are not enabled yet</h4>
                    <p>Payments are being set up on this store. Please check back soon — the library will let you complete your purchase as soon as payments go live.</p>
                    <p class="muted">Your order is saved and waiting. Nothing has been charged.</p>
                </div>

                <div class="payment-summary">
                    <p>Order total: <strong>{{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($order->total), $order->currency) }}</strong></p>
                </div>
            @endif

            @if($payment && $payment->isPending())
                {{-- The whole block is the polling anchor: its data-* URLs are
                     what the script below reads every couple of seconds, so the
                     panel can move from "waiting" to "paid" on its own. --}}
                <div class="flash note" data-payment-wait
                     data-status-url="{{ route('account.orders.payments.status', $order) }}"
                     data-refresh-url="{{ route('account.orders.payments.refresh', $order) }}"
                     data-order-url="{{ route('account.orders.show', $order) }}">
                    <h4>A payment is already in progress</h4>
                    @if($payment->isCard())
                        <p>Your card payment is completed on the secure Abliner page. When you come back, use the button below to check whether it went through.</p>
                    @elseif($payment->isControlNumber())
                        <p>Use the control number below to pay. When you have paid it, check the status.</p>
                    @else
                        <p>Approve the prompt on your phone — we are watching for the payment and unlock your books the moment it arrives.</p>
                        @if(filled($payment->phone))
                            <p class="muted">The request was sent to <strong>{{ strlen($payment->phone) >= 12 ? '0'.substr($payment->phone, 3) : $payment->phone }}</strong>.</p>
                        @endif
                    @endif

                    <p class="wait-live" data-wait-auto role="status">
                        <span class="wait-live__dot" aria-hidden="true"></span>
                        Checking for your payment automatically every 2 seconds. Leave this page open.
                    </p>

                    @if($canStartPayment && $payment->isMobile())
                        <p class="wait-other">
                            <button type="button" class="linkish" data-pay-other-number>Pay with a different phone number</button>
                        </p>
                        <p class="muted">Sending a request to another number does not cancel the one already on your phone — only approve the number you want to use.</p>
                    @endif
                </div>

                @if($payment->isControlNumber() && filled($payment->control_number))
                    <div class="payment-panel-inner">
                        <h3>Your control number</h3>
                        <p class="control-number">{{ $payment->control_number }}</p>
                        <p class="muted">Pay exactly {{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($order->total), $order->currency) }}. Use reference <strong>{{ $payment->control_number }}</strong>.</p>
                    </div>
                @endif

                <form method="POST" action="{{ route('account.orders.payments.refresh', $order) }}">
                    @csrf
                    <button class="btn btn-secondary" type="submit">Check payment status</button>
                    <p class="muted">Only needed if the automatic check cannot reach the payment network.</p>
                </form>
            @endif

            @if($canStartPayment)
                <form method="POST" action="{{ route('account.orders.payments.store', $order) }}" class="payment-form" data-payment-form
                      data-amount="{{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($order->total), $order->currency) }}">
                    @csrf
                    <input type="hidden" name="method" value="{{ old('method', $selectedMethod) }}" data-method-input>

                    {{-- The order the amount is taken from is fixed; nothing here
                         can change what is charged. --}}
                    <div class="form-section">
                        <h2>How would you like to pay?</h2>
                        <div class="network-grid">
                            @foreach($methods as $key => $method)
                                <label class="network-card method-{{ $key }}">
                                    <input type="radio" name="method_choice" value="{{ $key }}" data-method-choice
                                           {{ old('method', $selectedMethod) === $key ? 'checked' : '' }} required>
                                    <span class="network-brand">{{ $method['label'] }}</span>
                                    <em>{{ $method['hint'] }}</em>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Mobile money only: a push has to go somewhere. --}}
                    <div class="form-section" data-method-panel="mobile" hidden>
                        <h2>Select your mobile network</h2>
                        <div class="network-grid">
                            @foreach($networks as $key => $label)
                                <label class="network-card network-{{ $key }}">
                                    <input type="radio" name="network" value="{{ $key }}"
                                           {{ old('network', $selectedNetwork) === $key ? 'checked' : '' }}
                                           data-network-choice>
                                    <span class="network-brand">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('network')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-section" data-method-panel="mobile" hidden>
                        <h2>Your mobile money number</h2>
                        <p class="muted" data-phone-hint>
                            @if(auth()->user()->phone)
                                Filled in automatically from your account. You can change it below if you want to pay from a different number.
                            @else
                                We send the payment request to this number. Add one when you create your account and it will be filled in for you every time.
                            @endif
                        </p>
                        <input
                            type="text"
                            name="phone"
                            value="{{ old('phone', auth()->user()->phone) }}"
                            class="form-control"
                            placeholder="e.g. 0712 345 678"
                            maxlength="32"
                            inputmode="tel"
                            autocomplete="tel"
                            data-mobile-phone
                        >
                        <p class="field-warn" data-phone-warn hidden></p>
                        <button type="button" class="linkish" data-phone-change>Change number</button>
                        @error('phone')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="form-section">
                        <h2>Your details</h2>
                        <p class="muted">These are sent with the payment so your receipt is filled in correctly.</p>
                        <div class="row-half">
                            <div>
                                <label class="field-label" for="first_name">First name</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" value="{{ old('first_name', auth()->user()->first_name) }}" maxlength="100">
                                @error('first_name')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="field-label" for="last_name">Last name</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" value="{{ old('last_name', auth()->user()->last_name) }}" maxlength="100">
                                @error('last_name')
                                    <p class="field-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="payment-summary">
                        <p>Amount to pay: <strong>{{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($order->total), $order->currency) }}</strong></p>
                        <button class="btn btn-primary btn-lg" type="submit" data-pay-button>
                            Pay {{ \App\Support\Money::formatWhole(\App\Support\Money::toWholeInt($order->total), $order->currency) }}
                        </button>
                        <p class="muted" data-method-note>
                            You will receive a prompt on your phone to enter your PIN and approve the payment.
                        </p>
                    </div>
                </form>
            @endif

            {{-- Once a control number exists, the dial steps are the actual
                 instructions. Shown whether or not the form is still visible. --}}
            @if($payment && $payment->isControlNumber() && filled($payment->control_number) && $instructions !== [])
                <div class="payment-panel-inner">
                    <h3>How to pay from your phone</h3>

                    @foreach($instructions as $channel)
                        <div class="instructions-block">
                            <h4>{{ $channel['label'] }}</h4>
                            <p class="dial-code"><code>{{ $channel['code'] }}</code></p>
                            <ol>
                                @foreach($channel['steps'] as $step)
                                    <li>{{ $step }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach

                    <p class="muted">
                        Paying from a bank instead? Use the bank list below. Your reference number is
                        <strong>{{ $payment->control_number }}</strong>.
                    </p>
                </div>
            @endif
        </div>
    @endif
@endsection

@push('styles')
    <style>
        .network-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .network-card {
            display: block;
            border: 2px solid var(--border, #ddd);
            border-radius: 10px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s ease, transform .15s ease;
            background: #fff;
        }
        .network-card:hover { transform: translateY(-2px); }
        .network-card input { position: absolute; opacity: 0; pointer-events: none; }
        .network-card .network-brand { font-weight: var(--ebs-fw-ui, 600); display: block; }
        .network-card:has(input:checked) { border-color: #232f3e; box-shadow: 0 0 0 3px rgba(255, 153, 0, .25); }
        .network-card em {
            display: block;
            font-size: .85rem;
            font-style: normal;
            color: var(--muted, #6b7280);
            margin-top: 4px;
        }
        .network-airtel_money { border-color: #e70000; }
        .network-airtel_money:hover { border-color: #e70000; }
        .network-mpesa { border-color: #c42228; }
        .network-mpesa:hover { border-color: #c42228; }
        .network-mixx_yas { border-color: #8e44ad; }
        .network-mixx_yas:hover { border-color: #8e44ad; }
        .network-halotel { border-color: #f7941d; }
        .network-halotel:hover { border-color: #f7941d; }

        .control-number {
            font-size: 1.6rem;
            font-weight: 700;
            letter-spacing: .08em;
            margin: 8px 0;
        }
        .instructions-block {
            border-top: 1px solid var(--border, #e5e7eb);
            padding-top: 12px;
            margin-top: 12px;
        }
        .instructions-block:first-of-type { border-top: 0; margin-top: 0; padding-top: 0; }
        .instructions-block h4 { margin-bottom: 4px; }
        .instructions-block ol { margin-bottom: 8px; padding-left: 20px; }
        .dial-code { font-size: 1.1rem; margin-bottom: 8px; }
        .field-warn {
            margin: 8px 0 0;
            color: #9a6a00;
            font-weight: var(--ebs-fw-ui, 600);
            font-size: .85rem;
            line-height: 1.4;
        }
        .linkish {
            margin-top: 8px;
            padding: 0;
            border: 0;
            background: none;
            color: var(--ebs-orange, #f90);
            font-weight: var(--ebs-fw-ui, 600);
            font-size: .85rem;
            cursor: pointer;
            text-decoration: underline;
        }
        .wait-live {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 12px 0 0;
            font-size: .9rem;
            font-weight: var(--ebs-fw-ui, 600);
            color: var(--muted, #4b5563);
        }
        .wait-live__dot {
            flex: 0 0 auto;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #16a34a;
            animation: waitPulse 2s ease-out infinite;
        }
        @keyframes waitPulse {
            0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, .55); }
            70% { box-shadow: 0 0 0 9px rgba(22, 163, 74, 0); }
            100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
        }
        .wait-other { margin-top: 10px; }
        .wait-other .linkish { margin-top: 0; }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            var form = document.querySelector('[data-payment-form]');

            if (!form) return;

            var methodInput = form.querySelector('[data-method-input]');
            var panels = form.querySelectorAll('[data-method-panel]');
            var phone = form.querySelector('[data-mobile-phone]');
            var note = form.querySelector('[data-method-note]');
            var button = form.querySelector('[data-pay-button]');
            var phoneHint = form.querySelector('[data-phone-hint]');
            var phoneWarn = form.querySelector('[data-phone-warn]');
            var phoneChange = form.querySelector('[data-phone-change]');

            // Per-network phone hints. The networks overlap (Airtel runs on
            // both 06 and 07), so `first` is only a soft guide - it warns, it
            // never blocks the payment.
            var NETWORK_INFO = {
                airtel_money: { label: 'Airtel Money', example: '0712 345 678', first: '', hint: 'Airtel numbers start with 06 or 07, for example 0655 345 678.' },
                mpesa: { label: 'M-PESA', example: '0712 345 678', first: '7', hint: 'M-PESA works on Vodacom numbers, which start with 07, for example 0712 345 678.' },
                mixx_yas: { label: 'Mixx by Yas', example: '0612 345 678', first: '6', hint: 'Mixx by Yas works on Tigo numbers, which start with 06, for example 0612 345 678.' },
                halotel: { label: 'Halotel', example: '0622 345 678', first: '6', hint: 'Halotel numbers start with 06, for example 0622 345 678.' }
            };

            var DEFAULT_PLACEHOLDER = 'e.g. 0712 345 678';
            var DEFAULT_HINT = phoneHint ? phoneHint.textContent : '';

            function digits(value) {
                return (value || '').replace(/\D/g, '').replace(/^0/, '255').replace(/^255255/, '255');
            }

            function currentNetwork() {
                var checked = form.querySelector('input[name="network"]:checked');
                return checked ? checked.value : '';
            }

            function firstDigit(value) {
                var s = digits(value);
                return s.charAt(3) || '';
            }

            function softPhoneWarn(network, value) {
                if (!network) return '';
                var info = NETWORK_INFO[network];
                if (!info || !info.first) return '';

                var first = firstDigit(value);
                if (!first || first === info.first) return '';

                return info.first === '7'
                    ? 'This looks like a 06 number (Airtel, Mixx or Halotel), not an M-PESA number. Check the network you selected before you pay.'
                    : 'This looks like an M-PESA number (07), but ' + info.label + ' is a 06 network. Check the network you selected before you pay.';
            }

            function applyNetworkToPhone(network) {
                var info = NETWORK_INFO[network];

                if (phone) {
                    phone.placeholder = info ? info.example : DEFAULT_PLACEHOLDER;
                }

                if (phoneHint) {
                    phoneHint.textContent = info ? info.hint : DEFAULT_HINT;
                }

                if (phoneWarn) {
                    var message = softPhoneWarn(network, phone ? phone.value : '');
                    phoneWarn.textContent = message;
                    phoneWarn.hidden = !message;
                }
            }

            form.querySelectorAll('[data-network-choice]').forEach(function (choice) {
                choice.addEventListener('change', function () {
                    applyNetworkToPhone(choice.value);
                });
            });

            if (phone) {
                phone.addEventListener('input', function () {
                    applyNetworkToPhone(currentNetwork());
                });

                if (phoneChange) {
                    phoneChange.addEventListener('click', function () {
                        phone.focus();
                        phone.select();
                    });
                }
            }

            applyNetworkToPhone(currentNetwork());

            var notes = {
                mobile: 'You will receive a prompt on your phone to enter your PIN and approve the payment.',
                card: 'You will be taken to a secure Abliner page to enter your card details. We never see your card number.'
            };

            var labels = {
                mobile: 'Pay ',
                card: 'Pay securely by card '
            };

            // Show only the questions the chosen method actually needs. A card
            // payment has no phone number and no network, so asking for them
            // would be noise the customer has to guess past.
            function sync() {
                var method = methodInput.value;

                panels.forEach(function (panel) {
                    panel.hidden = panel.getAttribute('data-method-panel') !== method;
                });

                if (phone) {
                    phone.required = method === 'mobile';
                }

                if (note) {
                    note.textContent = notes[method] || '';
                }

                if (button && form.dataset.amount) {
                    button.textContent = (labels[method] || 'Pay ') + form.dataset.amount;
                }
            }

            form.querySelectorAll('[data-method-choice]').forEach(function (choice) {
                choice.addEventListener('change', function () {
                    methodInput.value = choice.value;
                    sync();
                });
            });

            sync();
        })();
    </script>

    <script>
        (function () {
            var wait = document.querySelector('[data-payment-wait]');
            if (!wait) return;

            var statusUrl = wait.getAttribute('data-status-url');
            var refreshUrl = wait.getAttribute('data-refresh-url');
            var orderUrl = wait.getAttribute('data-order-url');
            var tokenField = document.querySelector('form input[name="_token"]');

            var INTERVAL_MS = 2000;
            var MAX_ATTEMPTS = 120;
            // A verify the provider refuses (no reference yet, network down) is
            // not worth repeating every tick: fall back to the local read for a
            // few ticks before asking the provider again.
            var VERIFY_PAUSE_TICKS = 5;

            var attempts = 0;
            var verifyPause = 0;
            var timer = null;
            var inFlight = false;
            var stopped = false;

            function headers() {
                return { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            }

            function tokenBody() {
                var data = new FormData();
                data.append('_token', tokenField ? tokenField.value : '');
                return data;
            }

            function esc(value) {
                var node = document.createElement('span');
                node.textContent = value === null || value === undefined ? '' : String(value);
                return node.innerHTML;
            }

            function stop() {
                stopped = true;
                window.clearTimeout(timer);
                timer = null;
            }

            function schedule(delay) {
                window.clearTimeout(timer);
                if (stopped || document.hidden) return;
                timer = window.setTimeout(tick, delay);
            }

            function tick() {
                timer = null;
                if (stopped) return;

                attempts++;
                if (attempts > MAX_ATTEMPTS) { giveUp(); return; }

                if (verifyPause > 0) {
                    verifyPause--;
                    read();
                    return;
                }

                verify();
            }

            /*
             * The deliberate "ask the payment network" call. It reads the
             * payment AND settles it when the provider has approved it, so an
             * approval flips this panel on its own - the customer never has to
             * press "Check payment status" after entering their PIN.
             */
            function verify() {
                inFlight = true;

                fetch(refreshUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: headers(),
                    body: tokenBody()
                })
                    .then(function (response) {
                        return response.ok
                            ? response.json().catch(function () { return null; })
                            : null;
                    })
                    .then(function (body) {
                        if (body && body.state) { settle(body); return; }
                        verifyPause = VERIFY_PAUSE_TICKS;
                        read();
                    })
                    .catch(function () {
                        verifyPause = VERIFY_PAUSE_TICKS;
                        read();
                    });
            }

            // The cheap local read, used while the provider cannot be asked.
            function read() {
                inFlight = true;

                fetch(statusUrl, { credentials: 'same-origin', headers: headers() })
                    .then(function (response) { return response.json().catch(function () { return null; }); })
                    .then(settle)
                    .catch(retryLater);
            }

            function settle(state) {
                inFlight = false;
                handle(state);
            }

            function retryLater() {
                inFlight = false;
                schedule(INTERVAL_MS);
            }

            function handle(state) {
                if (stopped) return;

                if (!state) { schedule(INTERVAL_MS); return; }

                if (state.paid) { paid(); return; }

                if (state.state === 'pending') { schedule(INTERVAL_MS); return; }

                if (state.state === 'expired') { finish('expired', state); return; }

                if (state.state === 'failed') { finish('failed', state); return; }

                finish('idle', state);
            }

            function render(html) {
                wait.innerHTML = html;
            }

            function paid() {
                stop();
                render('<h4>Payment confirmed</h4>'
                    + '<p>Thank you \u2014 your order is paid and your books are ready. Taking you to your order\u2026</p>');

                window.setTimeout(function () { window.location.href = orderUrl; }, 900);
            }

            function restartButton() {
                return '<p class="wait-other"><button type="button" class="linkish" data-pay-restart>Start a new payment</button></p>';
            }

            function finish(kind, state) {
                stop();

                if (kind === 'expired') {
                    render('<h4>That request expired</h4>'
                        + '<p>The payment request timed out before it was approved, so you can safely start a new one.</p>'
                        + restartButton());
                    return;
                }

                if (kind === 'failed') {
                    var message = state && state.payment && state.payment.message
                        ? state.payment.message
                        : 'This payment did not go through. You can try again.';

                    render('<h4>Payment not completed</h4>'
                        + '<p>' + esc(message) + '</p>'
                        + restartButton());
                    return;
                }

                render('<h4>No payment is in progress</h4>'
                    + '<p>Start a new payment whenever you are ready.</p>'
                    + restartButton());
            }

            function giveUp() {
                stop();
                render('<h4>We have not seen the payment yet</h4>'
                    + '<p>The payment network has not reported this request. Press <strong>Check payment status</strong> below to look once more, or start a new payment.</p>'
                    + restartButton());
            }

            function focusPhone() {
                var field = document.querySelector('[data-mobile-phone]');
                if (!field) return;

                // The number only has a home on the mobile step: put that step
                // on screen first, otherwise the customer is scrolled to a
                // field they cannot see.
                var choice = document.querySelector('input[data-method-choice][value="mobile"]');
                if (choice && !choice.checked) {
                    choice.checked = true;
                    choice.dispatchEvent(new Event('change'));
                }

                field.scrollIntoView({ block: 'center' });
                field.focus();
                field.select();
            }

            function restart() {
                var panel = document.querySelector('[data-payment-form]');
                if (!panel) { window.location.reload(); return; }

                panel.scrollIntoView({ block: 'start' });

                var field = panel.querySelector('[data-mobile-phone]');
                var mobilePanel = field ? field.closest('[data-method-panel]') : null;

                if (field && mobilePanel && !mobilePanel.hidden) {
                    field.focus();
                    field.select();
                    return;
                }

                var choice = panel.querySelector('[data-method-choice]:checked')
                    || panel.querySelector('[data-method-choice]');
                if (choice) choice.focus();
            }

            // Delegated so it still works after the panel has been re-rendered
            // into a terminal state.
            document.addEventListener('click', function (e) {
                if (!e.target || !e.target.closest) return;

                if (e.target.closest('[data-pay-other-number]')) {
                    focusPhone();
                    return;
                }

                if (e.target.closest('[data-pay-restart]')) {
                    restart();
                }
            });

            // A background tab has nothing to poll: give up the timer and pick
            // it straight back up when the page is looked at again.
            document.addEventListener('visibilitychange', function () {
                if (document.hidden || stopped || inFlight || timer !== null) return;
                attempts = 0;
                schedule(INTERVAL_MS);
            });

            schedule(INTERVAL_MS);
        })();
    </script>
@endpush
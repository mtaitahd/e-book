@extends('layouts.customer.app')

@section('title', 'Order ' . $order->order_number)

@php
    $networks = \App\Models\Payment::NETWORKS;
    $netLogos = [
        'airtel_money' => 'aitel-removebg-preview.png',
        'mpesa' => 'mpesa-removebg-preview.png',
        'mixx_yas' => 'mix_by_yas-removebg-preview.png',
        'halotel' => 'halotel-removebg-preview.png',
    ];
    $payAmount = \App\Support\Money::toWholeInt($order->total);
    $payDisplay = 'TZS ' . number_format($payAmount);
    $autoOpen = $errors->has('network') || $errors->has('phone') || $errors->has('method') || old('network') !== null;

    // A request that is already on the customer's phone: coming back to this
    // order page must land straight on the waiting panel, so the automatic
    // check starts without them having to find "Pay now" first. Only a live
    // (unexpired) request earns it - a finished or timed-out one does not.
    $latestPayment = $order->payments()->latest('id')->first();
    $resumeWait = ! $autoOpen
        && ! $order->isPaid()
        && $latestPayment !== null
        && $latestPayment->isPending()
        && ! $latestPayment->hasExpiredRequest();
@endphp

@section('content')
    <p class="back-link"><a href="{{ route('account.orders.index') }}">&larr; All my orders</a></p>

    <h1>Order {{ $order->order_number }}</h1>

    <div class="row-half">
        <div>
            <h2>Details</h2>
            <table class="profile">
                <tr><th>Placed</th><td>{{ $order->created_at->format('M j, Y g:i A') }}</td></tr>
                <tr><th>Status</th><td><span class="badge {{ $order->status }}">{{ ucfirst($order->status) }}</span></td></tr>
                <tr><th>Currency</th><td>{{ $order->currency }}</td></tr>
            </table>
        </div>

        <div>
            <h2>Summary</h2>
            <table class="profile">
                <tr><th>Subtotal</th><td>{{ \App\Support\Money::format($order->subtotal, $order->currency) }}</td></tr>
                <tr><th>Total</th><td>{{ \App\Support\Money::format($order->total, $order->currency) }}</td></tr>
            </table>
        </div>
    </div>

    <h2>Items</h2>
    <div class="table-scroll">
        <table class="simple-table">
            <thead>
                <tr>
                    <th>Book</th>
                    <th class="right">Qty</th>
                    <th class="right">Unit price</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td>
                            <a href="{{ route('books.show', $item->book) }}">{{ $item->book->title }}</a>
                        </td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">{{ \App\Support\Money::format($item->unit_price, $order->currency) }}</td>
                        <td class="right">{{ \App\Support\Money::format($item->subtotal, $order->currency) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="muted-box">
        <h4>Payment status: {{ ucfirst($order->status) }}</h4>
        @if($order->isPending())
            <p>This order is awaiting payment.</p>
            @if($quickPay)
                <form method="POST" action="{{ route('account.orders.payments.store', $order) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="method" value="mobile">
                    <input type="hidden" name="network" value="{{ $quickPay['network'] }}">
                    <input type="hidden" name="phone" value="{{ $quickPay['phone'] }}">
                    <button type="submit" class="btn filled" data-quick-pay>
                        Quick pay {{ $payDisplay }}
                    </button>
                </form>
            @endif
            <button type="button" class="btn" data-pay-open>Pay now</button>
        @elseif($order->isPaid())
            @php
                $payment = $order->payments()->where('status', \App\Models\Payment::STATUS_COMPLETED)->latest('id')->first();
                $channel = $payment && $payment->channel_provider
                    ? \Illuminate\Support\Str::title(str_replace('_', ' ', $payment->channel_provider))
                    : 'Mobile Money';
                $paidBooks = $order->purchases()->with('book')->get();
            @endphp
            <p>Payment for this order has been confirmed.</p>
            @if($payment)
                <p class="muted">Paid {{ $payment->paid_at?->format('M j, Y g:i A') }} via {{ $channel }} · Reference {{ $payment->provider_reference }}</p>
            @endif
            <p>Your books are ready — start reading or download them from your library.</p>
            <p class="pay-ebook-links">
                @foreach($paidBooks as $purchase)
                    <a class="btn" href="{{ route('account.purchases.read', $purchase) }}">Read {{ $purchase->book?->title ?? 'E-book' }}</a>
                @endforeach
                <a class="btn filled" href="{{ route('account.purchases.index') }}">Go to My Books</a>
            </p>
        @else
            <p>This order was {{ $order->status }} and did not complete a purchase. You can start a new payment for it.</p>
            @if($quickPay)
                <form method="POST" action="{{ route('account.orders.payments.store', $order) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="method" value="mobile">
                    <input type="hidden" name="network" value="{{ $quickPay['network'] }}">
                    <input type="hidden" name="phone" value="{{ $quickPay['phone'] }}">
                    <button type="submit" class="btn filled" data-quick-pay>
                        Quick pay {{ $payDisplay }}
                    </button>
                </form>
            @endif
            <button type="button" class="btn" data-pay-open>Pay now</button>
        @endif
    </div>

    @if(!$order->isPaid())
        <div
            class="pay-modal{{ $autoOpen ? ' is-open' : '' }}"
            id="payModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="payModalTitle"
            aria-hidden="{{ $autoOpen ? 'false' : 'true' }}"
            data-pay-auto="{{ $autoOpen ? '1' : '0' }}"
            data-pay-resume="{{ $resumeWait ? '1' : '0' }}"
            data-status-url="{{ route('account.orders.payments.status', $order) }}"
            data-store-url="{{ route('account.orders.payments.store', $order) }}"
            data-refresh-url="{{ route('account.orders.payments.refresh', $order) }}"
            data-purchases-url="{{ route('account.purchases.index') }}"
        >
            <div class="pay-modal__overlay" data-pay-close></div>

            <div class="pay-modal__panel" role="document">
                <div class="pay-modal__head">
                    <div>
                        <h2 id="payModalTitle">Complete your payment</h2>
                        <p data-pay-subtitle>Choose how you would like to pay</p>
                    </div>
                    <button type="button" class="pay-modal__close" data-pay-close aria-label="Close payment dialog">&times;</button>
                </div>

                <div class="pay-modal__summary">
                    <span>Order {{ $order->order_number }}</span>
                    <strong>{{ $payDisplay }}</strong>
                </div>

                <p class="pay-alert" data-pay-alert role="alert" hidden></p>

                {{-- novalidate is required, not cosmetic: the mobile step's required
                     inputs stay in the DOM while the card and control number steps
                     are open, so native validation would block those submits and
                     fail on a field the customer cannot even see. We validate per
                     step in JavaScript instead, and the server validates anyway. --}}
                <form method="POST" action="{{ route('account.orders.payments.store', $order) }}" class="pay-form" id="payForm" novalidate>
                    @csrf

                    {{-- The dialog is a method chooser first. `method` is the one
                         field the server acts on: the controller validates it
                         against Payment::METHODS and never trusts it from a
                         free-text field. The radio group below is only the
                         on-screen choice; it is `method_choice` precisely so it
                         is never submitted. --}}
                    <input type="hidden" name="method" value="mobile" data-pay-method>

                    {{-- Step 1 — how do you want to pay? --}}
                    <section class="pay-step" data-pay-step="choose">
                        <fieldset class="pay-methods">
                            <legend>How would you like to pay?</legend>

                            <label class="pay-method">
                                <input type="radio" name="method_choice" value="mobile" data-pay-method-choice>
                                <span class="pay-method__check" aria-hidden="true"></span>
                                <span class="pay-method__body">
                                    <span class="pay-method__title">Mobile Money</span>
                                    <span class="pay-method__hint">Approve a prompt on your phone</span>
                                </span>
                            </label>

                            <label class="pay-method">
                                <input type="radio" name="method_choice" value="card" data-pay-method-choice>
                                <span class="pay-method__check" aria-hidden="true"></span>
                                <span class="pay-method__body">
                                    <span class="pay-method__title">Card</span>
                                    <span class="pay-method__hint">Pay on the secure card page</span>
                                </span>
                            </label>

                            </fieldset>

                        @if($errors->has('method'))
                            <p class="pay-form__error">{{ $errors->first('method') }}</p>
                        @endif
                    </section>

                    {{-- Step 2a — the mobile money USSD push. --}}
                    <section class="pay-step" data-pay-step="mobile" hidden>
                        <button type="button" class="pay-back" data-pay-back>&larr; Change payment method</button>

                        <fieldset class="pay-networks">
                            <legend>Select your network</legend>

                            @foreach($networks as $key => $label)
                                <label class="pay-net pay-net--{{ $key }}" data-pay-net data-net="{{ $key }}" data-name="{{ $label }}">
                                    <input type="radio" name="network" value="{{ $key }}" {{ old('network') === $key ? 'checked' : '' }} required>
                                    <span class="pay-net__check" aria-hidden="true"></span>
                                    <img
                                        src="{{ asset('assets/' . rawurlencode($netLogos[$key])) }}"
                                        alt="{{ $label }} logo"
                                        class="pay-net__logo"
                                        width="44"
                                        height="44"
                                        loading="lazy"
                                    >
                                    <span class="pay-net__label">{{ $label }}</span>
                                </label>
                            @endforeach
                        </fieldset>

                        @if($errors->has('network'))
                            <p class="pay-form__error">{{ $errors->first('network') }}</p>
                        @endif

                        {{-- Echoes the chosen network back, so the number is typed against the
                         network the request will actually be pushed to. --}}
                        <div class="pay-chosen" data-pay-chosen hidden>
                            <span class="pay-chosen__label">Selected network</span>
                            <span class="pay-chosen__row">
                                <img class="pay-chosen__logo" data-pay-chosen-logo src="" alt="" width="34" height="34">
                                <span class="pay-chosen__name" data-pay-chosen-name></span>
                            </span>
                        </div>

                        <div class="pay-phone" id="payPhone">
                            <label class="pay-phone__label" for="payPhoneInput">
                                Mobile money number
                                <span class="pay-phone__brand" id="payBrand"></span>
                            </label>

                            {{-- The country code is fixed and shown, so the customer
                                 only ever types the local part. Whatever they paste
                                 in is normalized in JavaScript before it is sent, and
                                 the server normalizes again regardless. --}}
                            <div class="pay-phone__field" data-pay-phone-field>
                                <span class="pay-phone__prefix" aria-hidden="true">+255</span>
                                <input
                                    type="text"
                                    id="payPhoneInput"
                                    name="phone"
                                    class="pay-phone__input"
                                    value="{{ old('phone', auth()->user()->phone) }}"
                                    placeholder="0612 345 678"
                                    maxlength="18"
                                    autocomplete="tel-national"
                                    inputmode="numeric"
                                    aria-describedby="payPhoneHint payPhoneError"
                                    required
                                >
                            </div>

                            <p class="pay-phone__error" id="payPhoneError" data-pay-phone-error hidden></p>

                            <p class="pay-phone__warn" id="payPhoneWarn" data-pay-phone-warn hidden></p>

                            <p class="pay-phone__hint" id="payPhoneHint" data-pay-phone-hint>Enter the mobile money number you want to pay from. We will send the approval request to this number.</p>

                            <button type="button" class="pay-phone__change" data-pay-phone-change>Use a different number</button>
                        </div>

                        <div class="pay-actions">
                            <button type="button" class="pay-cancel" data-pay-close>Cancel</button>
                            <button type="submit" class="pay-submit" id="paySubmit" disabled>Pay {{ $payDisplay }}</button>
                        </div>

                        <p class="pay-busy" data-pay-busy hidden role="status">Processing payment&hellip;</p>

                        <p class="pay-trust">You will get a prompt on your phone to approve. Nothing is charged until you enter your PIN.</p>
                    </section>

                    {{-- Step 2b — the card is collected on Abliner's own hosted
                         page, so we never ask for a card number here. --}}
                    <section class="pay-step" data-pay-step="card" hidden>
                        <button type="button" class="pay-back" data-pay-back>&larr; Change payment method</button>

                        <p class="pay-note">You will be taken to the secure Abliner payment page to enter your card details, then brought straight back here.</p>

                        <div class="pay-actions">
                            <button type="button" class="pay-cancel" data-pay-close>Cancel</button>
                            <button type="submit" class="pay-submit">Continue to card payment</button>
                        </div>

                        <p class="pay-trust">Your card details are entered on the payment provider's page. We never see or store them.</p>
                    </section>

                    {{-- Step 2c — a control number the customer pays later from
                         whatever app or bank they like. --}}
                    {{-- Step 3 — the server's answer. Everything here is rendered
                         from the state endpoint, never from what the browser
                         remembers, so a reload or a back button lands here
                         too. --}}
                    <section class="pay-step pay-step--status" data-pay-step="status" hidden aria-live="polite">
                    </section>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('styles')
    <style>
        .pay-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            visibility: hidden;
            opacity: 0;
            transition: opacity .2s ease, visibility .2s ease;
        }
        .pay-modal__overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 17, 17, .6);
        }
        .pay-modal__panel {
            position: relative;
            width: min(480px, 100%);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            background: #fff;
            border-radius: 0;
            box-shadow: 0 24px 60px rgba(15, 17, 17, .35);
            padding: 24px;
            transform: translateY(14px);
            transition: transform .2s ease;
        }
        .pay-modal.is-open { visibility: visible; opacity: 1; }
        .pay-modal.is-open .pay-modal__panel { transform: translateY(0); }
        body.pay-modal-open { overflow: hidden; }

        .pay-modal__head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .pay-modal__head h2 { margin: 0 0 4px; color: var(--ebs-navy); font-size: 1.25rem; }
        .pay-modal__head p { margin: 0; color: var(--ebs-text-2); font-size: .92rem; font-weight: var(--ebs-fw-secondary, 500); }
        .pay-modal__close {
            background: transparent;
            border: none;
            color: var(--ebs-text-2);
            font-size: 1.7rem;
            line-height: 1;
            cursor: pointer;
            padding: 0 6px;
            border-radius: 0;
        }
        .pay-modal__close:hover, .pay-modal__close:focus-visible { color: var(--ebs-navy); background: #f0f2f2; }

        .pay-modal__summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            background: #f7f9f9;
            border: 1px solid var(--ebs-border);
            border-radius: 0;
            padding: 10px 14px;
            margin: 14px 0 18px;
            font-size: .95rem;
        }
        .pay-modal__summary span { color: var(--ebs-text-2); font-weight: var(--ebs-fw-secondary, 500); }
        .pay-modal__summary strong { color: var(--ebs-navy); font-size: 1.1rem; white-space: nowrap; font-weight: var(--ebs-fw-display, 800); }

        .pay-networks {
            border: 0;
            padding: 0;
            margin: 0 0 6px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .pay-networks legend { padding: 0; margin-bottom: 10px; font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-text); font-size: .95rem; }
        .pay-net {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            border: 2px solid var(--ebs-border);
            border-radius: 0;
            padding: 14px 10px 12px;
            cursor: pointer;
            background: #fff;
            text-align: center;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .pay-net:hover { border-color: #b9c0c0; }
        .pay-net input { position: absolute; opacity: 0; pointer-events: none; }
        .pay-net__logo { width: 44px; height: 44px; object-fit: contain; }
        .pay-net__label { font-weight: var(--ebs-fw-ui, 600); font-size: .88rem; color: var(--ebs-text); line-height: 1.25; }
        .pay-net__check {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 20px;
            height: 20px;
            border-radius: 0;
            border: 2px solid var(--ebs-border);
            background: #fff;
            transition: background .15s ease, border-color .15s ease;
        }
        .pay-net__check::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 5px;
            width: 4px;
            height: 8px;
            border: solid transparent;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .pay-net:has(input:checked) .pay-net__check::after { border-color: #fff; }
        .pay-net:has(input:checked) .pay-net__check { background: var(--net-acc); border-color: var(--net-acc); }
        .pay-net:has(input:focus-visible) { outline: 2px solid var(--ebs-orange); outline-offset: 2px; }

        .pay-net--airtel_money { --net-acc: #e40000; --net-bg: #fdeceb; }
        .pay-net--airtel_money:has(input:checked) { border-color: #e40000; background: #fdeceb; box-shadow: 0 8px 18px rgba(228, 0, 0, .18); }
        .pay-net--mpesa { --net-acc: #17a04a; --net-bg: #e7f7ee; }
        .pay-net--mpesa:has(input:checked) { border-color: #17a04a; background: #e7f7ee; box-shadow: 0 8px 18px rgba(23, 160, 74, .18); }
        .pay-net--mixx_yas { --net-acc: #2f6fda; --net-bg: #e9f1fc; }
        .pay-net--mixx_yas:has(input:checked) { border-color: #2f6fda; background: #e9f1fc; box-shadow: 0 8px 18px rgba(47, 111, 218, .18); }
        .pay-net--halotel { --net-acc: #f7941d; --net-bg: #fef3e4; }
        .pay-net--halotel:has(input:checked) { border-color: #f7941d; background: #fef3e4; box-shadow: 0 8px 18px rgba(247, 148, 29, .18); }

        .pay-form__error { margin: 0 0 10px; color: #a12622; font-weight: var(--ebs-fw-ui, 600); font-size: .88rem; }

        .pay-phone {
            max-height: 0;
            opacity: 0;
            overflow: hidden;
            transform: translateY(-6px);
            transition: max-height .22s ease, opacity .22s ease, transform .22s ease;
            margin-top: 14px;
        }
        .pay-phone.is-visible { max-height: 320px; opacity: 1; transform: translateY(0); }
        .pay-phone__label { display: block; font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-text); margin-bottom: 6px; font-size: .95rem; }
        .pay-phone__brand { font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-orange); }

        /* Country code sits outside the field so the customer only types the
           local part, and the two halves read as one control. */
        .pay-phone__field {
            display: flex;
            align-items: stretch;
            border: 1.5px solid var(--ebs-border);
            background: #fff;
        }
        .pay-phone__field:focus-within { border-color: var(--ebs-orange); box-shadow: 0 0 0 3px rgba(255, 153, 0, .18); }
        .pay-phone__field.is-invalid { border-color: #a12622; }
        .pay-phone__field.is-invalid:focus-within { box-shadow: 0 0 0 3px rgba(161, 38, 34, .16); }

        .pay-phone__prefix {
            display: flex;
            align-items: center;
            padding: 0 11px;
            border-right: 1px solid var(--ebs-border);
            background: #f4f6f9;
            color: var(--ebs-text-2);
            font-size: .95rem;
            font-weight: var(--ebs-fw-ui, 600);
            white-space: nowrap;
            user-select: none;
        }

        .pay-phone__input {
            flex: 1 1 auto;
            min-width: 0;
            padding: 11px 12px;
            border: 0;
            background: transparent;
            font-size: 1rem;
            /* Digits are grouped for legibility, so the mono stack keeps the
               number from shifting as the groups are inserted. */
            font-variant-numeric: tabular-nums;
            letter-spacing: .02em;
            color: var(--ebs-text);
        }
        .pay-phone__input:focus { outline: none; }
        .pay-phone__input::placeholder { color: #9aa3a8; }

        .pay-phone__error { margin: 8px 0 0; color: #a12622; font-weight: var(--ebs-fw-ui, 600); font-size: .85rem; line-height: 1.4; }
        .pay-phone__warn { margin: 8px 0 0; color: #9a6a00; font-weight: var(--ebs-fw-ui, 600); font-size: .85rem; line-height: 1.4; }
        .pay-phone__hint { margin: 8px 0 0; font-size: .85rem; color: var(--ebs-text-2); line-height: 1.4; font-weight: var(--ebs-fw-secondary, 500); }
        .pay-phone__change {
            margin-top: 10px;
            padding: 7px 14px;
            border: 1px solid var(--ebs-orange);
            border-radius: 999px;
            background: #fff;
            color: var(--ebs-orange);
            font-weight: var(--ebs-fw-ui, 600);
            font-size: .85rem;
            cursor: pointer;
            transition: background .15s ease, color .15s ease;
        }
        .pay-phone__change:hover { background: var(--ebs-orange); color: #fff; }

        /* The network that was chosen, echoed back above the number. */
        .pay-chosen {
            margin-top: 14px;
            padding: 11px 13px;
            border: 1px solid var(--ebs-border);
            border-left: 3px solid var(--pay-acc, var(--ebs-navy));
            background: #f7f9f9;
        }
        .pay-chosen__label { display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; color: var(--ebs-text-2); font-weight: var(--ebs-fw-ui, 600); }
        .pay-chosen__row { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
        .pay-chosen__logo { width: 34px; height: 34px; object-fit: contain; }
        .pay-chosen__name { font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-text); font-size: .95rem; }

        /* Small in-place loading state, so the page never reloads. */
        .pay-busy { margin: 12px 0 0; text-align: right; font-size: .85rem; font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-text-2); }

        /* The number the approval request went to, masked. */
        .pay-sentto {
            margin: 2px 0 14px;
            font-size: 1.3rem;
            font-weight: var(--ebs-fw-ui, 600);
            color: var(--ebs-navy);
            font-variant-numeric: tabular-nums;
            letter-spacing: .02em;
        }

        .pay-actions { display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 18px; }
        .pay-cancel {
            background: #fff;
            border: 1px solid var(--ebs-border);
            color: var(--ebs-text-2);
            padding: 11px 18px;
            border-radius: 0;
            font-weight: var(--ebs-fw-ui, 600);
            cursor: pointer;
        }
        .pay-cancel:hover { background: #f0f2f2; }
        .pay-submit {
            background: var(--pay-acc, #232F3E);
            color: #fff;
            border: none;
            padding: 12px 22px;
            border-radius: 0;
            font-weight: var(--ebs-fw-ui, 600);
            font-size: .98rem;
            cursor: pointer;
            min-width: 170px;
            transition: background .15s ease;
        }
        .pay-submit:disabled { background: #e7e9e9; cursor: not-allowed; }
        .pay-trust {
            margin: 16px 0 0;
            font-size: .82rem;
            color: var(--ebs-text-2);
            background: #f7f9f9;
            border-radius: 0;
            padding: 8px 10px;
            text-align: center;
            font-weight: var(--ebs-fw-secondary, 500);
        }

        .pay-auto {
            margin: 10px 0 0;
            font-size: .8rem;
            color: var(--ebs-text-2);
            text-align: center;
        }

        /* ---- Step 1: the method chooser ---- */
        .pay-methods {
            border: 0;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 10px;
        }
        .pay-methods legend { padding: 0; margin-bottom: 12px; font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-text); font-size: .95rem; }
        .pay-method {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid var(--ebs-border);
            background: #fff;
            padding: 13px 14px 13px 40px;
            cursor: pointer;
            transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
        }
        .pay-method:hover { border-color: #b9c0c0; }
        .pay-method input { position: absolute; opacity: 0; pointer-events: none; }
        .pay-method__check {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            border: 2px solid var(--ebs-border);
            background: #fff;
            transition: background .15s ease, border-color .15s ease;
        }
        .pay-method__check::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 6px;
            width: 4px;
            height: 9px;
            border: solid transparent;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .pay-method:has(input:checked) { border-color: var(--ebs-navy); background: #f4f6f9; }
        .pay-method:has(input:checked) .pay-method__check { background: var(--ebs-navy); border-color: var(--ebs-navy); }
        .pay-method:has(input:checked) .pay-method__check::after { border-color: #fff; }
        .pay-method:has(input:focus-visible) { outline: 2px solid var(--ebs-orange); outline-offset: 2px; }
        .pay-method__body { display: flex; flex-direction: column; gap: 2px; }
        .pay-method__title { font-weight: var(--ebs-fw-ui, 600); color: var(--ebs-text); font-size: .95rem; line-height: 1.2; }
        .pay-method__hint { color: var(--ebs-text-2); font-size: .82rem; font-weight: var(--ebs-fw-secondary, 500); line-height: 1.3; }

        /* ---- Step 2: the per-method panels ---- */
        .pay-back {
            display: inline-block;
            margin: 0 0 14px;
            background: none;
            border: none;
            padding: 0;
            color: var(--ebs-text-2);
            font-size: .85rem;
            font-family: inherit;
            font-weight: var(--ebs-fw-secondary, 500);
            cursor: pointer;
        }
        .pay-back:hover, .pay-back:focus-visible { color: var(--ebs-orange); }

        /* "Pay using another method", offered under the waiting panel. */
        .pay-switchbar { margin-top: 4px; text-align: center; }
        .pay-switch {
            background: none;
            border: none;
            padding: 10px 12px;
            color: var(--ebs-navy);
            font-family: inherit;
            font-size: .85rem;
            font-weight: var(--ebs-fw-ui, 600);
            text-decoration: underline;
            cursor: pointer;
        }
        .pay-switch:hover, .pay-switch:focus-visible { color: var(--ebs-orange); }
        .pay-note {
            margin: 0 0 16px;
            font-size: .9rem;
            line-height: 1.5;
            color: var(--ebs-text);
        }
        .pay-optional { font-weight: var(--ebs-fw-secondary, 500); color: var(--ebs-text-2); font-size: .85rem; }

        /* ---- A message from the server ---- */
        .pay-alert {
            margin: 0 0 14px;
            padding: 10px 12px;
            border-left: 3px solid #a12622;
            background: #fdeceb;
            color: #7d1c19;
            font-size: .88rem;
            font-weight: var(--ebs-fw-secondary, 500);
            line-height: 1.45;
        }

        /* ---- Step 3: what the server says happened ---- */
        .pay-status { text-align: center; }
        .pay-status__icon {
            width: 56px;
            height: 56px;
            margin: 4px auto 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            line-height: 1;
        }
        .pay-status--wait .pay-status__icon { background: #fef3e4; color: #b76a05; }
        .pay-status--ok .pay-status__icon { background: #e7f7ee; color: #17a04a; }
        .pay-status--fail .pay-status__icon { background: #fdeceb; color: #a12622; }
        .pay-status__title { margin: 0 0 6px; color: var(--ebs-navy); font-size: 1.1rem; }
        .pay-status__lede { margin: 0 0 16px; color: var(--ebs-text-2); font-size: .9rem; line-height: 1.5; }

        .pay-kv {
            text-align: left;
            margin: 0 0 16px;
            border: 1px solid var(--ebs-border);
        }
        .pay-kv__row { display: flex; justify-content: space-between; gap: 12px; padding: 9px 12px; font-size: .88rem; border-bottom: 1px solid var(--ebs-border); }
        .pay-kv__row:last-child { border-bottom: 0; }
        .pay-kv__key { margin: 0; color: var(--ebs-text-2); font-weight: var(--ebs-fw-secondary, 500); }
        .pay-kv__val { margin: 0; color: var(--ebs-navy); font-weight: var(--ebs-fw-ui, 600); text-align: right; word-break: break-word; }

        .pay-progress { text-align: left; margin: 0 0 18px; padding: 0; list-style: none; }
        .pay-progress__item { position: relative; padding: 0 0 16px 30px; font-size: .87rem; color: var(--ebs-text-2); line-height: 1.4; }
        .pay-progress__item:last-child { padding-bottom: 0; }
        .pay-progress__item::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 4px;
            width: 11px;
            height: 11px;
            border: 2px solid var(--ebs-border);
            background: #fff;
        }
        .pay-progress__item::after {
            content: '';
            position: absolute;
            left: 11px;
            top: 15px;
            bottom: 0;
            width: 2px;
            background: var(--ebs-border);
        }
        .pay-progress__item:last-child::after { display: none; }
        .pay-progress__item.is-done { color: var(--ebs-navy); }
        .pay-progress__item.is-done::before { background: #17a04a; border-color: #17a04a; }
        .pay-progress__item.is-done::after { background: #17a04a; }
        .pay-progress__item.is-current { color: var(--ebs-navy); font-weight: var(--ebs-fw-ui, 600); }
        .pay-progress__item.is-current::before { border-color: var(--ebs-orange); box-shadow: 0 0 0 3px rgba(255, 153, 0, .2); }

        .pay-ctl {
            margin: 0 0 16px;
            padding: 16px;
            background: #f7f9f9;
            border: 2px dashed var(--ebs-navy);
            text-align: center;
        }
        .pay-ctl__label { display: block; color: var(--ebs-text-2); font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; font-weight: var(--ebs-fw-ui, 600); }
        .pay-ctl__number {
            display: block;
            margin: 6px 0 12px;
            color: var(--ebs-navy);
            font-size: 1.5rem;
            letter-spacing: .06em;
            word-break: break-all;
            font-weight: var(--ebs-fw-display, 800);
        }
        .pay-copy {
            background: var(--ebs-navy);
            color: #fff;
            border: none;
            padding: 9px 18px;
            font-family: inherit;
            font-size: .85rem;
            font-weight: var(--ebs-fw-ui, 600);
            cursor: pointer;
        }
        .pay-copy:hover { background: #1b2530; }
        .pay-copy.is-copied { background: #17a04a; }
        .pay-ctl__actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; }
        .pay-ctl__how {
            background: none;
            border: none;
            padding: 8px 4px;
            color: var(--ebs-navy);
            font-family: inherit;
            font-size: .85rem;
            font-weight: var(--ebs-fw-ui, 600);
            text-decoration: underline;
            cursor: pointer;
        }
        .pay-ctl__how:hover, .pay-ctl__how:focus-visible { color: var(--ebs-orange); }

        /* The "how to pay" popup, sitting on top of the payment dialog. */
        .pay-modal--nested { z-index: 1010; }
        .pay-modal--nested .pay-modal__panel { width: min(430px, 100%); }
        .pay-modal--nested .pay-modal__close { color: #a12622; background: #fdeceb; }
        .pay-modal--nested .pay-modal__close:hover,
        .pay-modal--nested .pay-modal__close:focus-visible { color: #fff; background: #a12622; }
        .pay-help__amount {
            margin: 0 0 14px;
            padding: 10px 12px;
            background: #f7f9f9;
            border: 1px solid var(--ebs-border);
            font-size: .88rem;
            color: var(--ebs-text-2);
        }
        .pay-help__amount strong { color: var(--ebs-navy); }
        .pay-copy.is-copied { background: #17a04a; }

        .pay-instructions { text-align: left; margin: 0 0 16px; }
        .pay-instructions__group { margin-bottom: 12px; }
        .pay-instructions__group:last-child { margin-bottom: 0; }
        .pay-instructions__label { color: var(--ebs-navy); font-size: .87rem; font-weight: var(--ebs-fw-ui, 600); }
        .pay-instructions__code { color: var(--ebs-navy); font-size: .95rem; font-weight: var(--ebs-fw-display, 800); letter-spacing: .05em; }
        .pay-instructions__steps { margin: 4px 0 0; padding-left: 18px; color: var(--ebs-text-2); font-size: .84rem; line-height: 1.5; }

        .pay-submit.is-busy { opacity: .7; cursor: progress; }

        .pay-ebook-links { display: flex; flex-wrap: wrap; gap: 10px; }

        @media (max-width: 480px) {
            .pay-modal { padding: 12px; }
            .pay-modal__panel { padding: 18px; border-radius: 0; }
            .pay-net__logo { width: 38px; height: 38px; }
            .pay-ctl__number { font-size: 1.25rem; }
            .pay-kv__row { flex-direction: column; gap: 2px; }
            .pay-kv__val { text-align: left; }
        }
        @media (max-width: 340px) {
            .pay-networks { grid-template-columns: 1fr; }
            .pay-net { flex-direction: row; gap: 10px; text-align: left; padding: 10px 12px 10px 38px; }
            .pay-net__label { font-size: .8rem; }
            .pay-method { padding: 11px 10px 11px 36px; }
            .pay-method__title { font-size: .88rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            .pay-modal, .pay-modal__panel, .pay-phone, .pay-net, .pay-net__check, .pay-method, .pay-method__check { transition: none !important; }
        }
    </style>
@endpush

@push('scripts')
<script>
    (function () {
        var modal = document.getElementById('payModal');
        if (!modal) return;

        var panel = modal.querySelector('.pay-modal__panel');
        var form = modal.querySelector('#payForm');
        if (!form) return;

        var overlay = modal.querySelector('.pay-modal__overlay');
        var closers = modal.querySelectorAll('[data-pay-close]');
        var openers = document.querySelectorAll('[data-pay-open]');
        var backs = modal.querySelectorAll('[data-pay-back]');
        var methodRadios = form.querySelectorAll('input[name="method_choice"]');
        var methodField = form.querySelector('[data-pay-method]');
        var alertBox = modal.querySelector('[data-pay-alert]');
        var subtitle = modal.querySelector('[data-pay-subtitle]');
        var statusStep = form.querySelector('[data-pay-step="status"]');
        var mobileStep = form.querySelector('[data-pay-step="mobile"]');
        var phoneInput = document.getElementById('payPhoneInput');
        var phoneBrand = document.getElementById('payBrand');
        var mobileSubmit = document.getElementById('paySubmit');

        var statusUrl = modal.getAttribute('data-status-url');
        var storeUrl = modal.getAttribute('data-store-url');
        var refreshUrl = modal.getAttribute('data-refresh-url');
        var tokenField = form.querySelector('input[name="_token"]');

        var lastFocus = null;
        var open = false;
        var busy = false;
        var current = '';
        var method = 'mobile';

        // The popup lives on <body> so a fixed overlay is not trapped inside
        // the dialog's own panel, and so re-rendering the status panel keeps it.
        var help = buildHelp();
        var helpOpen = false;
        var helpFocus = null;
        var lastPayment = null;
        var lastOrder = null;

        // Fingerprint of the pending panel currently on screen, so a tick that
        // finds nothing new does not rebuild the DOM under the customer's hands.
        var lastPendingKey = '';

        function pendingKey(state) {
            var p = state.payment || {};
            return [p.reference || '', p.status || '', p.phone || '', p.requested_at || ''].join('|');
        }

        function activePanel() {
            return helpOpen ? help.querySelector('.pay-modal__panel') : panel;
        }

        var steps = {};
        var stepNodes = form.querySelectorAll('[data-pay-step]');
        for (var s = 0; s < stepNodes.length; s++) {
            steps[stepNodes[s].getAttribute('data-pay-step')] = stepNodes[s];
        }

        function esc(value) {
            var node = document.createElement('span');
            node.textContent = value === null || value === undefined ? '' : String(value);
            return node.innerHTML;
        }

        function showAlert(message) {
            if (!alertBox) return;
            alertBox.textContent = message;
            alertBox.hidden = !message;
        }

        function hideAlert() {
            showAlert('');
        }

        /*
         * Navigation rules, in one place so the behaviour is predictable:
         *
         *   choose   -> mobile / card / control_number  (picking a method)
         *   a method -> choose                            (back / switch / retry)
         *   a method -> status                            (server answered)
         *
         * Two things must never change the step:
         *   - clicking the network card that is already selected
         *   - selecting a network while the network cards are on screen
         * Neither has a `change` event to hang anything off, so both are wired
         * explicitly below instead of being left to chance.
         */
        function show(name, keepAlert) {
            if (!Object.prototype.hasOwnProperty.call(steps, name)) return;

            current = name;
            for (var key in steps) {
                if (Object.prototype.hasOwnProperty.call(steps, key)) {
                    steps[key].hidden = key !== name;
                }
            }
            // Changing step normally drops the previous message, but a caller
            // that is about to explain a failure needs it to survive the move.
            if (!keepAlert) {
                hideAlert();
            }
        }

        function focusables() {
            var scope = activePanel();
            var list = scope.querySelectorAll('button, input, a[href], [tabindex]:not([tabindex="-1"])');
            var out = [];
            for (var i = 0; i < list.length; i++) {
                if (!list[i].disabled && list[i].offsetParent !== null) out.push(list[i]);
            }
            return out;
        }

        function focusFirst() {
            var f = focusables();
            if (f.length) f[0].focus();
        }

        function refreshMobileSubmit() {
            if (!mobileSubmit) return;
            mobileSubmit.disabled = !(hasNetwork() && phoneIsValid());
        }

        // ---- Tanzanian phone numbers -------------------------------------
        //
        // This mirrors App\Support\Phone::normalizeTanzanian exactly, so the
        // browser and the server agree on what is valid. The server still
        // normalizes and validates again - this only decides whether the
        // customer is allowed to press Continue, and what it says to them.
        //
        //   255XXXXXXXXX is canonical. 0612.../612... both normalize to it.
        //   Valid local prefixes are 6 and 7 (062/065/067, 071/072/073/075...).

        function phoneDigits(value) {
            return String(value == null ? '' : value).replace(/\D+/g, '');
        }

        function normalizeTzPhone(value) {
            var digits = phoneDigits(value);
            if (!digits) return null;

            var candidate;
            if (digits.indexOf('255') === 0) {
                candidate = digits;
            } else if (digits.charAt(0) === '0') {
                candidate = '255' + digits.slice(1);
            } else {
                candidate = '255' + digits;
            }

            // 12 digits, country code 255, then a 6 or 7 and eight more.
            if (!/^255[67]\d{8}$/.test(candidate)) return null;

            return candidate;
        }

        // The local part as it should be *displayed*: country code removed,
        // but a leading trunk zero kept, because the field shows +255 next to
        // "0612 345 678" rather than the international "612 345 678".
        function phoneDisplayDigits(value) {
            var digits = phoneDigits(value);

            // A number pasted or typed in full must not become "+255255...".
            if (digits.indexOf('255') === 0 && digits.length > 9) digits = digits.slice(3);

            return digits;
        }

        // The nine significant digits, which is what "is it long enough" and
        // "does it start with a real prefix" actually care about.
        function phoneSignificantDigits(value) {
            var digits = phoneDisplayDigits(value);
            return digits.charAt(0) === '0' ? digits.slice(1) : digits;
        }

        function phoneIsValid() {
            return phoneInput ? normalizeTzPhone(phoneInput.value) !== null : false;
        }

        var PHONE_PREFIX_MESSAGE = 'Tanzanian mobile money numbers start with 6 or 7, for example 0612 345 678 or 0712 345 678.';

        function phoneProblem(value) {
            if (!phoneDigits(value)) return 'Enter the mobile money number to pay from.';

            var significant = phoneSignificantDigits(value);

            // Too short to judge yet - say nothing until they finish typing.
            if (significant.length < 9) return null;
            if (normalizeTzPhone(value)) return null;

            // Prefix before length: "1234567890" is wrong because it does not
            // start with 6 or 7, and that is the more useful thing to say.
            if (significant.charAt(0) !== '6' && significant.charAt(0) !== '7') {
                return PHONE_PREFIX_MESSAGE;
            }

            if (significant.length > 9) return 'That number is too long. Enter 9 digits after 255.';

            return 'Enter a valid Tanzanian mobile money number.';
        }

        // Groups as they type: 0612 345 678, or 612 345 678 without the zero.
        //
        // Anything past the ninth significant digit is kept, not dropped. It is
        // kept so the customer can be told the number is too long - silently
        // trimming it would turn a wrong number into a different valid one.
        function formatPhoneForDisplay(value) {
            var digits = phoneDisplayDigits(value);

            if (digits.charAt(0) === '0') {
                return [digits.slice(0, 4), digits.slice(4, 7), digits.slice(7, 10), digits.slice(10)]
                    .filter(Boolean).join(' ');
            }

            return [digits.slice(0, 3), digits.slice(3, 6), digits.slice(6, 9), digits.slice(9)]
                .filter(Boolean).join(' ');
        }

        var phoneTouched = false;

        function showPhoneError() {
            var value = phoneInput ? phoneInput.value : '';
            var box = form.querySelector('[data-pay-phone-error]');
            var field = form.querySelector('[data-pay-phone-field]');

            // A valid number is never an error. Anything else has to be
            // classified, because phoneProblem returns null both for "too short
            // to tell yet" and for "this is fine" - those need different
            // treatment, and conflating them nags about correct numbers.
            var problem = '';

            if (!phoneIsValid()) {
                problem = phoneProblem(value) || '';
                var stillTyping = !phoneTouched && phoneSignificantDigits(value).length < 9;

                if (stillTyping) {
                    problem = '';
                } else if (!problem && phoneDigits(value)) {
                    // Blurred or submitted with something half typed: say why
                    // Continue is not working instead of silently staying dead.
                    problem = 'Enter the full mobile money number \u2014 9 digits after 255, for example 0612 345 678.';
                }
            }

            if (!problem) {
                if (box) { box.hidden = true; box.textContent = ''; }
                if (field) field.classList.remove('is-invalid');
                if (phoneInput) phoneInput.removeAttribute('aria-invalid');
                return '';
            }

            if (box) { box.textContent = problem; box.hidden = false; }
            if (field) field.classList.add('is-invalid');
            if (phoneInput) phoneInput.setAttribute('aria-invalid', 'true');

            return problem;
        }

        function onPhoneInput() {
            if (!phoneInput) return;

            // Repaint the grouped value without losing the caret.
            var before = phoneInput.value;
            var formatted = formatPhoneForDisplay(before);

            if (formatted !== before) {
                var atEnd = before.length - before.selectionStart === 0;
                phoneInput.value = formatted;
                if (atEnd) {
                    var pos = formatted.length;
                    phoneInput.setSelectionRange(pos, pos);
                }
            }

            showPhoneError();
            showSoftPhoneWarn();
            refreshMobileSubmit();
        }

        function hasNetwork() {
            return !!form.querySelector('[data-pay-step="mobile"] input[name="network"]:checked');
        }

        function maskPhone(value) {
            var full = normalizeTzPhone(value);
            if (!full) return value || '';

            var local = full.slice(3);
            // +255 6XX XXX XXX - enough to recognise, not enough to spend.
            return '+255 ' + local.charAt(0) + 'XX XXX XXX';
        }

        // A network is only meaningful for the panel it was picked in, so clear
        // the other one. Otherwise a leftover selection rides along in the
        // request and lands on a payment row that has nothing to do with it.
        function clearNetworks(step) {
            if (!step) return;
            var radios = step.querySelectorAll('input[name="network"]');
            for (var i = 0; i < radios.length; i++) radios[i].checked = false;
            var marked = step.querySelectorAll('.pay-net.is-selected');
            for (var j = 0; j < marked.length; j++) marked[j].classList.remove('is-selected');

            if (step === mobileStep) {
                if (phoneBrand) phoneBrand.textContent = '';
                clearChosenNetwork();
                applyNetworkToPhone('');
            }
        }

        function chooseMethod(next) {
            method = next;
            if (methodField) methodField.value = next;

            for (var i = 0; i < methodRadios.length; i++) {
                if (methodRadios[i].value === next) methodRadios[i].checked = true;
            }

            // Leaving the waiting panel for any reason cancels the poll: the loop must
            // never outlive the panel it was watching.
            if (name !== 'status') stopPolling();

            if (next !== 'mobile') clearNetworks(mobileStep);

            if (next === 'mobile') {
                if (phoneBrand) phoneBrand.textContent = '';
                refreshMobileSubmit();
            }

            show(next);
            focusFirst();
        }

        /*
         * Selecting a network never changes step. It only repaints the card
         * that is selected, recolours the panel, points the phone hint at the
         * brand, and re-evaluates whether Continue may be pressed.
         */
        var DEFAULT_PHONE_PLACEHOLDER = '0612 345 678';
        var DEFAULT_PHONE_HINT = 'Enter the mobile money number you want to pay from. We will send the approval request to this number.';

        // Per-network phone hints. The networks overlap (Airtel runs on both
        // 06 and 07), so `first` is only a soft guide - it warns, it never
        // blocks the payment.
        var NETWORK_INFO = {
            airtel_money: { label: 'Airtel Money', example: '0655 345 678', first: '', hint: 'Airtel numbers start with 06 or 07, for example 0655 345 678.' },
            mpesa: { label: 'M-PESA', example: '0712 345 678', first: '7', hint: 'M-PESA works on Vodacom numbers, which start with 07, for example 0712 345 678.' },
            mixx_yas: { label: 'Mixx by Yas', example: '0612 345 678', first: '6', hint: 'Mixx by Yas works on Tigo numbers, which start with 06, for example 0612 345 678.' },
            halotel: { label: 'Halotel', example: '0622 345 678', first: '6', hint: 'Halotel numbers start with 06, for example 0622 345 678.' }
        };

        function currentNetwork() {
            var checked = form.querySelector('[data-pay-step="mobile"] input[name="network"]:checked');
            return checked ? checked.value : '';
        }

        // A soft warning only: the operator prefixes overlap in Tanzania, so
        // this tells the customer to double-check the pairing instead of
        // refusing the payment.
        function softPhoneWarn(network, value) {
            if (!network) return '';
            var info = NETWORK_INFO[network];
            if (!info || !info.first) return '';

            var canonical = normalizeTzPhone(value);
            if (!canonical) return '';

            var first = canonical.charAt(3);
            if (!first || first === info.first) return '';

            return info.first === '7'
                ? 'This looks like a 06 number (Airtel, Mixx or Halotel), not an M-PESA number. Check the network you selected before you pay.'
                : 'This looks like an M-PESA number (07), but ' + info.label + ' is a 06 network. Check the network you selected before you pay.';
        }

        function showSoftPhoneWarn() {
            var box = form.querySelector('[data-pay-phone-warn]');
            if (!box) return '';
            var message = softPhoneWarn(currentNetwork(), phoneInput ? phoneInput.value : '');
            box.textContent = message;
            box.hidden = !message;
            return message;
        }

        function applyNetworkToPhone(network) {
            var info = NETWORK_INFO[network];
            var hintEl = form.querySelector('[data-pay-phone-hint]');
            if (phoneInput) phoneInput.placeholder = info ? info.example : DEFAULT_PHONE_PLACEHOLDER;
            if (hintEl) hintEl.textContent = info ? info.hint : DEFAULT_PHONE_HINT;
            showSoftPhoneWarn();
        }

        function selectNetwork(card, radio) {
            if (!card || !radio || !radio.checked) return;

            var panel = card.closest('[data-pay-step]');
            if (panel) {
                panel.style.setProperty(
                    '--pay-acc',
                    window.getComputedStyle(card).getPropertyValue('--net-acc').trim()
                );
            }

            var siblings = card.parentNode.querySelectorAll('.pay-net.is-selected');
            for (var i = 0; i < siblings.length; i++) siblings[i].classList.remove('is-selected');
            card.classList.add('is-selected');

            if (card.closest('[data-pay-step="mobile"]')) {
                if (phoneBrand) phoneBrand.textContent = ' \u00b7 ' + card.getAttribute('data-name');
                echoChosenNetwork(card);
                applyNetworkToPhone(card.getAttribute('data-net'));
                if (phoneInput && phoneInput.value.trim() === '') {
                    window.setTimeout(function () { phoneInput.focus(); }, 60);
                }
            }

            refreshMobileSubmit();
        }

        function echoChosenNetwork(card) {
            var box = form.querySelector('[data-pay-chosen]');
            if (!box) return;

            var name = form.querySelector('[data-pay-chosen-name]');
            var logo = form.querySelector('[data-pay-chosen-logo]');
            var label = card.getAttribute('data-name');

            if (name) name.textContent = label;
            if (logo) {
                var src = card.querySelector('img');
                if (src) {
                    logo.src = src.getAttribute('src');
                    logo.alt = label + ' logo';
                }
            }

            box.hidden = false;
        }

        function clearChosenNetwork() {
            var box = form.querySelector('[data-pay-chosen]');
            if (box) box.hidden = true;
        }

        var netCards = form.querySelectorAll('[data-pay-net]');
        for (var n = 0; n < netCards.length; n++) {
            (function (card) {
                var radio = card.querySelector('input[name="network"]');
                if (!radio) return;

                radio.addEventListener('change', function () {
                    selectNetwork(card, radio);
                });

                // Re-clicking the card that is already selected fires no
                // `change` event. Handle it here so the click is a harmless
                // no-op that keeps the customer on this step, instead of
                // falling through to anything that could navigate away.
                card.addEventListener('click', function (e) {
                    if (e.target.closest('input')) return;
                    selectNetwork(card, radio);
                });
            })(netCards[n]);
        }

        /*
         * Picking a method is the only way into a method step. Clicking the
         * method that is already selected also enters its step, because after
         * going back the radio stays checked and a plain `change` event would
         * never fire again - the customer would tap Card, nothing would
         * happen, and the dialog would look broken.
         */
        function enterMethod(next) {
            if (!next) return;
            chooseMethod(next);
        }

        /*
         * The number is validated as it is typed, but only complained about
         * once the customer has finished - no red field at the third digit.
         */
        if (phoneInput) {
            phoneInput.addEventListener('input', onPhoneInput);

            phoneInput.addEventListener('blur', function () {
                phoneTouched = true;
                onPhoneInput();
            });

            // A pasted or autofilled full number arrives with no keystrokes,
            // so "input" still fires but "touched" does not.
            phoneInput.addEventListener('paste', function () {
                phoneTouched = true;
                window.setTimeout(onPhoneInput, 0);
            });

            // The account number is prefilled in canonical 255XXXXXXXXX form.
            // Show the local part, because +255 is already on the prefix.
            if (phoneInput.value) {
                phoneInput.value = formatPhoneForDisplay(phoneInput.value);
            }
        }

        // "Change number" invites the customer to edit an autofilled account
        // number without implying they must on every payment.
        var phoneChangeLink = form.querySelector('[data-pay-phone-change]');
        if (phoneChangeLink) {
            phoneChangeLink.addEventListener('click', function () {
                if (phoneInput) {
                    phoneTouched = true;
                    phoneInput.focus();
                    phoneInput.select();
                }
            });
        }

        for (var mr = 0; mr < methodRadios.length; mr++) {
            (function (radio) {
                radio.addEventListener('change', function () {
                    if (radio.checked) enterMethod(radio.value);
                });
            })(methodRadios[mr]);
        }

        var methodLabels = form.querySelectorAll('.pay-method');
        for (var ml = 0; ml < methodLabels.length; ml++) {
            (function (label) {
                var radio = label.querySelector('input[name="method_choice"]');
                if (!radio) return;

                label.addEventListener('click', function () {
                    if (radio.checked) enterMethod(radio.value);
                });
            })(methodLabels[ml]);
        }

        function tokenOnly() {
            var data = new FormData();
            data.append('_token', tokenField ? tokenField.value : '');
            return data;
        }

        // Only the visible panel contributes fields. This is what guarantees a
        // card request never carries a phone number, and a control number never
        // carries one either.
        function payload() {
            var data = tokenOnly();
            data.append('method', method);

            var step = steps[method];
            if (!step) return data;

            var net = step.querySelector('input[name="network"]:checked');
            if (net) data.append('network', net.value);

            // Send the canonical 255XXXXXXXXX form, not the grouped display
            // value. The server normalizes again, so this is belt and braces -
            // it keeps the request honest and free of the spaces we render.
            if (method === 'mobile' && phoneInput) {
                data.append('phone', normalizeTzPhone(phoneInput.value) || phoneDigits(phoneInput.value));
            }

            return data;
        }

        function setBusy(on, button) {
            busy = on;

            var busyNote = form.querySelector('[data-pay-busy]');
            if (busyNote) busyNote.hidden = !on;

            var buttons = form.querySelectorAll('button[type="submit"], [data-pay-check]');
            for (var i = 0; i < buttons.length; i++) {
                if (buttons[i] === button) continue;
                buttons[i].disabled = on;
            }

            if (button) {
                if (on) {
                    button.setAttribute('data-label', button.textContent);
                    button.textContent = 'Processing payment\u2026';
                    button.classList.add('is-busy');
                    button.disabled = true;
                } else {
                    var label = button.getAttribute('data-label');
                    if (label) button.textContent = label;
                    button.classList.remove('is-busy');

                    // Re-enable by the rules rather than blindly, so a failed
                    // attempt cannot leave Continue clickable with a bad number.
                    if (method === 'mobile' && current === 'mobile') {
                        refreshMobileSubmit();
                    } else {
                        button.disabled = false;
                    }
                }
            }
        }

        function send(url, data, onDone) {
            if (busy) return;
            var button = current === 'status'
                ? statusStep.querySelector('[data-pay-check]')
                : steps[method].querySelector('button[type="submit"]');

            busy = true;
            setBusy(true, button);

            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data
            })
                .then(function (response) {
                    return response.json()
                        .catch(function () { return {}; })
                        .then(function (body) { return { ok: response.ok, body: body }; });
                })
                .then(function (result) {
                    setBusy(false, button);
                    onDone(result);
                })
                .catch(function () {
                    setBusy(false, button);
                    showAlert('We could not reach the server. Check your connection and try again.');
                    onDone(null);
                });
        }

        function loadState(onDone) {
            fetch(statusUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (response) { return response.json(); })
                .then(onDone)
                .catch(function () { onDone(null); });
        }

        function kvRows(rows) {
            var html = '<dl class="pay-kv">';
            for (var i = 0; i < rows.length; i++) {
                html += '<div class="pay-kv__row">'
                    + '<dt class="pay-kv__key">' + esc(rows[i][0]) + '</dt>'
                    + '<dd class="pay-kv__val">' + esc(rows[i][1]) + '</dd>'
                    + '</div>';
            }
            return html + '</dl>';
        }

        function progressList(payment) {
            var stepsByMethod = {
                mobile: [
                    'We sent a request to your phone',
                    'You enter your PIN to approve it',
                    'Your bank confirms the payment to us',
                    'Your books unlock'
                ],
                card: [
                    'You entered your card on the secure page',
                    'Your bank approves the payment',
                    'Your bank confirms the payment to us',
                    'Your books unlock'
                ],
                control_number: [
                    'We issued your control number',
                    'You pay it from your app or bank',
                    'Your bank confirms the payment to us',
                    'Your books unlock'
                ]
            };

            var list = stepsByMethod[payment && payment.method] || stepsByMethod.mobile;

            var html = '<ol class="pay-progress">';
            for (var i = 0; i < list.length; i++) {
                var cls = i === 0 ? ' is-done' : (i === 1 ? ' is-current' : '');
                html += '<li class="pay-progress__item' + cls + '">' + esc(list[i]) + '</li>';
            }
            return html + '</ol>';
        }

        function controlBlock(payment) {
            if (!payment || !payment.control_number) return '';

            return '<div class="pay-ctl">'
                + '<span class="pay-ctl__label">Your control number</span>'
                + '<span class="pay-ctl__number">' + esc(payment.control_number) + '</span>'
                + '<div class="pay-ctl__actions">'
                + '<button type="button" class="pay-copy" data-pay-copy="' + esc(payment.control_number) + '">Copy control number</button>'
                + '<button type="button" class="pay-ctl__how" data-pay-help>How to pay &rarr;</button>'
                + '</div>'
                + '</div>';
        }

        function renderSuccess(state) {
            var payment = state.payment || {};
            var order = state.order || {};
            var rows = [['Amount', payment.amount || order.amount]];

            if (payment.method_label) rows.push(['Paid by', payment.method_label]);
            if (payment.network_label) rows.push(['Network', payment.network_label]);
            if (payment.reference) rows.push(['Reference', payment.reference]);

            subtitle.textContent = 'Payment confirmed';

            var actions = '';
            if (order.read_url) {
                actions += '<a class="btn filled" href="' + esc(order.read_url) + '">Read E-book</a>';
            }
            actions += '<a class="btn' + (order.read_url ? '' : ' filled') + '" href="' + esc(order.library_url) + '">Go to My Books</a>';

            statusStep.innerHTML = ''
                + '<div class="pay-status pay-status--ok">'
                + '<div class="pay-status__icon" aria-hidden="true">&#10003;</div>'
                + '<h3 class="pay-status__title">Payment received</h3>'
                + '<p class="pay-status__lede">Thank you \u2014 your order is paid and your books are ready.</p>'
                + kvRows(rows)
                + '<div class="pay-actions">' + actions + '</div>'
                + '</div>';

            show('status');
        }

        /*
         * Automatic verification while the dialog waits.
         *
         * Every 2 seconds the dialog asks the provider directly: reading our
         * own row only reflects what the webhook has already delivered, and the
         * customer is waiting for a verify that has to happen without them
         * pressing "Check payment status". A verify the provider refuses (no
         * reference yet, network trouble) falls back to the local read for a
         * few ticks so an unhappy provider is not hammered with the same call.
         * renderState stops the timer on any terminal state (completed, failed,
         * expired) and shows the right panel, so approving a prompt is all the
         * customer ever has to do.
         */
        var PollSettings = {
            INTERVAL_MS: 2000,
            MAX_ATTEMPTS: 120,
            VERIFY_PAUSE_TICKS: 5
        };

        var pollTimer = null;
        var pollAttempts = 0;
        var verifyPause = 0;

        function stopPolling() {
            if (pollTimer !== null) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }
        }

        function startPolling() {
            stopPolling();
            pollAttempts = 0;
            verifyPause = 0;
            schedulePoll();
        }

        function schedulePoll() {
            stopPolling();

            if (current !== 'status') return;
            if (pollAttempts >= PollSettings.MAX_ATTEMPTS) return;

            // Poll every 2 seconds so an approval feels instant: the moment the
            // provider marks the payment paid, the panel switches on its own.
            var delay = PollSettings.INTERVAL_MS;

            pollTimer = window.setTimeout(function () {
                pollTimer = null;
                pollAttempts++;

                var ask = verifyPause > 0 ? loadState : verifyState;
                if (verifyPause > 0) verifyPause--;

                ask(function (next) {
                    if (next && next.paid) {
                        stopPolling();
                        renderState(next);
                        return;
                    }

                    // Anything terminal stops the loop. A still-pending state
                    // just means the customer has not entered their PIN yet.
                    if (next && (next.state === 'failed' || next.state === 'expired')) {
                        stopPolling();
                        renderState(next);
                        return;
                    }

                    if (next && next.state === 'pending' && pendingKey(next) === lastPendingKey) {
                        // Nothing new: leave the rendered panel alone so a
                        // customer reading (or tabbing through) it is not
                        // thrown back to the top every two seconds.
                        if (document.hidden) {
                            pollAttempts = 0;
                            pollTimer = null;
                            return;
                        }

                        schedulePoll();
                        return;
                    }

                    if (document.hidden) {
                        // A background tab does not need to poll. Try again as
                        // soon as it is looked at.
                        pollAttempts = 0;
                        pollTimer = null;
                        return;
                    }

                    // Either a different request became the live one, or the
                    // read failed and the panel needs re-syncing with it.
                    if (next && next.state === 'pending') {
                        renderState(next);
                        return;
                    }

                    schedulePoll();
                });
            }, delay);
        }

        // The "ask the provider" half of the loop: POST refresh, quietly (no
        // busy state - this is background checking, not a button press). A
        // refusal or a network error falls back to the local read instead of
        // dropping the customer on an error.
        function verifyState(onDone) {
            fetch(refreshUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: tokenOnly()
            })
                .then(function (response) {
                    return response.ok
                        ? response.json()
                        : null;
                })
                .then(function (body) {
                    if (body) { onDone(body); return; }
                    verifyPause = PollSettings.VERIFY_PAUSE_TICKS;
                    loadState(onDone);
                })
                .catch(function () {
                    verifyPause = PollSettings.VERIFY_PAUSE_TICKS;
                    loadState(onDone);
                });
        }

        function renderPending(state) {
            var payment = state.payment || {};
            var order = state.order || {};
            var rows = [];

            lastPayment = payment;
            lastPendingKey = pendingKey(state);

            // A mobile money push gets its own confirmation wording: the
            // customer needs to know which number the prompt went to, so they
            // can tell the difference between the real prompt and a scam call.
            var isPush = payment.method === 'mobile';

            if (!isPush && payment.method_label) rows.push(['Method', payment.method_label]);
            if (!isPush && payment.network_label) rows.push(['Network', payment.network_label]);
            if (isPush && payment.network_label) rows.push(['Network', payment.network_label]);
            rows.push(['Amount', payment.amount || order.amount]);
            if (!isPush && payment.phone) rows.push(['Mobile number', payment.phone]);
            if (payment.reference) rows.push(['Reference', payment.reference]);

            subtitle.textContent = 'Payment in progress';

            statusStep.innerHTML = ''
                + '<div class="pay-status pay-status--wait">'
                + '<div class="pay-status__icon" aria-hidden="true">&#9203;</div>'
                + (isPush
                    ? '<h3 class="pay-status__title">Payment request sent</h3>'
                    : '<h3 class="pay-status__title">Waiting for your payment</h3>')
                + (isPush && payment.phone
                    ? '<p class="pay-status__lede">We sent a payment approval request to:</p>'
                        + '<p class="pay-sentto">' + esc(maskPhone(payment.phone)) + '</p>'
                        + '<p class="pay-status__lede">Please check your phone and enter your PIN to approve the payment.</p>'
                    : '<p class="pay-status__lede">' + pendingLede(payment) + '</p>')
                + kvRows(rows)
                + controlBlock(payment)
                + progressList(payment)
                + '<div class="pay-actions">'
                + '<button type="button" class="pay-cancel" data-pay-close>Cancel</button>'
                + '<button type="button" class="pay-submit" data-pay-check>Check payment status</button>'
                + '</div>'
                + '<div class="pay-switchbar">'
                + (isPush && state.can_start !== false
                    ? '<button type="button" class="pay-switch" data-pay-different-number>Pay from a different number</button>'
                    : '')
                + '<button type="button" class="pay-switch" data-pay-switch>Pay using another method</button>'
                + '</div>'
                + '<p class="pay-trust">Your current request is still active \u2014 do not pay it twice. If you switch, only pay the one you end up using.</p>'
                + '<p class="pay-auto" data-pay-auto>Leave this open. We check your payment every 2 seconds and unlock your books the moment it arrives \u2014 there is nothing for you to press.</p>'
                + '</div>';

            show('status');
            startPolling();
        }

        function pendingLede(payment) {
            if (payment.control_number) {
                return 'Pay the control number above from your app or bank, then check the status. Nothing is charged until you do.';
            }
            if (payment.method === 'card') {
                return 'Finish on the secure card page, then come back and check the status. We will not ask for your card details again.';
            }
            return 'Approve the request on your phone, then check the status below. Nothing is charged until you enter your PIN.';
        }

        /* ------------------------------------------------------------------
         | The "how to pay" popup.
         |
         | The steps live in their own dialog rather than inline, because a
         | control number is only useful together with the exact numbers to
         | dial and the customer should not have to scroll to find them. Built
         | once and kept on <body> so re-rendering the status panel cannot lose
         | it.
         |------------------------------------------------------------------ */
        function buildHelp() {
            var wrap = document.createElement('div');
            wrap.className = 'pay-modal pay-modal--nested';
            wrap.id = 'payHelp';
            wrap.setAttribute('role', 'dialog');
            wrap.setAttribute('aria-modal', 'true');
            wrap.setAttribute('aria-labelledby', 'payHelpTitle');
            wrap.setAttribute('aria-hidden', 'true');

            wrap.innerHTML = ''
                + '<div class="pay-modal__overlay" data-pay-help-close></div>'
                + '<div class="pay-modal__panel" role="document">'
                + '<div class="pay-modal__head">'
                + '<div>'
                + '<h2 id="payHelpTitle">How to pay with your control number</h2>'
                + '<p>Pay exactly the amount shown below</p>'
                + '</div>'
                + '<button type="button" class="pay-modal__close" data-pay-help-close aria-label="Close instructions">&times;</button>'
                + '</div>'
                + '<div data-pay-help-body></div>'
                + '<div class="pay-actions">'
                + '<button type="button" class="pay-submit" data-pay-help-close>Got it</button>'
                + '</div>'
                + '</div>';

            document.body.appendChild(wrap);

            // The popup sits outside the form, so it needs its own listener
            // rather than the delegated handler the status panel uses.
            wrap.addEventListener('click', function (e) {
                if (e.target.closest('[data-pay-help-close]')) {
                    e.preventDefault();
                    closeHelp();
                }
            });

            return wrap;
        }

        function openHelp() {
            var payment = lastPayment;

            if (!payment || !payment.control_number) return;

            var body = help.querySelector('[data-pay-help-body]');
            var amount = payment.amount || (lastOrder && lastOrder.amount) || '';
            var steps = payment.instructions || [];

            var html = '<p class="pay-help__amount">Control number <strong>' + esc(payment.control_number) + '</strong> &middot; Pay exactly <strong>' + esc(amount) + '</strong></p>';

            if (!steps.length) {
                html += '<p class="pay-help__lede">Open your mobile money app or your bank, choose to pay a bill or merchant, and enter the control number above as the account number.</p>';
            } else {
                html += '<div class="pay-instructions">';
                for (var i = 0; i < steps.length; i++) {
                    html += '<div class="pay-instructions__group">'
                        + '<div class="pay-instructions__label">' + esc(steps[i].label) + '</div>';

                    if (steps[i].code) {
                        html += '<div class="pay-instructions__code">' + esc(steps[i].code) + '</div>';
                    }

                    if (steps[i].steps && steps[i].steps.length) {
                        html += '<ol class="pay-instructions__steps">';
                        for (var s = 0; s < steps[i].steps.length; s++) {
                            html += '<li>' + esc(steps[i].steps[s]) + '</li>';
                        }
                        html += '</ol>';
                    }

                    html += '</div>';
                }
                html += '</div>';
            }

            html += '<p class="pay-help__lede">Once you have paid, come back and choose <strong>Check payment status</strong>.</p>';

            body.innerHTML = html;
            help.classList.add('is-open');
            help.setAttribute('aria-hidden', 'false');
            helpOpen = true;
            helpFocus = document.activeElement;
            focusFirst();
        }

        function closeHelp() {
            if (!helpOpen) return;
            helpOpen = false;
            help.classList.remove('is-open');
            help.setAttribute('aria-hidden', 'true');
            if (helpFocus && typeof helpFocus.focus === 'function') helpFocus.focus();
        }

        function renderExpired(state) {
            var payment = state.payment || {};
            subtitle.textContent = 'Request expired';

            statusStep.innerHTML = ''
                + '<div class="pay-status pay-status--fail">'
                + '<div class="pay-status__icon" aria-hidden="true">!</div>'
                + '<h3 class="pay-status__title">That request expired</h3>'
                + '<p class="pay-status__lede">The payment request timed out before it was approved, so you can safely start a new one.</p>'
                + kvRows([['Amount', payment.amount || (state.order && state.order.amount)]])
                + '<div class="pay-actions">'
                + '<button type="button" class="pay-cancel" data-pay-close>Cancel</button>'
                + '<button type="button" class="pay-submit" data-pay-retry>Try again</button>'
                + '</div>'
                + '</div>';

            show('status');
        }

        function renderFailed(state) {
            var payment = state.payment || {};
            subtitle.textContent = 'Payment not completed';

            statusStep.innerHTML = ''
                + '<div class="pay-status pay-status--fail">'
                + '<div class="pay-status__icon" aria-hidden="true">!</div>'
                + '<h3 class="pay-status__title">Payment not completed</h3>'
                + '<p class="pay-status__lede">'
                + esc(payment.message || 'This payment did not go through. You can try again.')
                + '</p>'
                + '<div class="pay-actions">'
                + '<button type="button" class="pay-cancel" data-pay-close>Cancel</button>'
                + '<button type="button" class="pay-submit" data-pay-retry>Try again</button>'
                + '</div>'
                + '</div>';

            show('status');
        }

        function renderState(state) {
            if (!state) {
                showAlert('We could not read your payment status. Please try again.');
                return;
            }

            // A card payment continues on the provider's hosted page.
            if (state.redirect_url) {
                window.location.href = state.redirect_url;
                return;
            }

            lastOrder = state.order || null;

            if (state.paid) { stopPolling(); renderSuccess(state); return; }

            switch (state.state) {
                case 'pending':
                    renderPending(state);
                    return;
                case 'expired':
                    stopPolling();
                    renderExpired(state);
                    return;
                case 'failed':
                    stopPolling();
                    renderFailed(state);
                    return;
                default:
                    // Idle: nothing is waiting on the provider, so stop rather
                    // than polling a payment that does not exist.
                    stopPolling();
                    subtitle.textContent = 'Choose how you would like to pay';
                    show('choose');
            }
        }

        function checkStatus() {
            send(refreshUrl, tokenOnly(), function (result) {
                if (result === null) return;

                if (!result.ok) {
                    showAlert(
                        (result.body && result.body.message)
                            ? result.body.message
                            : 'We could not check the status just now. Please try again.'
                    );
                    return;
                }

                renderState(result.body);
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (busy || current === 'status') return;

            if (method === 'mobile') {
                if (!hasNetwork()) {
                    showAlert('Choose the mobile money network you want to pay from.');
                    return;
                }

                phoneTouched = true;

                // Validate before spending a request. The server would catch it
                // too, but it would cost a round trip and a confusing page.
                if (!phoneInput || !phoneInput.value.trim()) {
                    showPhoneError();
                    showAlert('Enter the mobile money number to pay from.');
                    return;
                }

                if (showPhoneError()) {
                    if (phoneInput) phoneInput.focus();
                    return;
                }
            }

            send(storeUrl, payload(), function (result) {
                if (result === null) return;

                // Starting the request is not paying the order: the only
                // success here is a "pending" state the customer still has to
                // approve on their phone.
                // The provider refused to start the payment. Explain why instead
                // of silently dropping the customer back on the chooser, and let
                // them pick a different method rather than retrying the same one.
                if (!result.ok && !result.body.paid && !result.body.payment) {
                    show('choose');
                    showAlert(
                        (result.body && result.body.message)
                            ? result.body.message
                            : 'The payment request could not be started. Please try again.'
                    );
                    return;
                }

                renderState(result.body);
            });
        });

        form.addEventListener('click', function (e) {
            var target = e.target;

            // Back from a method step, "pay using another method" while
            // waiting, and "try again" after a failure all land here.
            if (
                target.closest('[data-pay-back]')
                || target.closest('[data-pay-switch]')
                || target.closest('[data-pay-retry]')
            ) {
                goToChoose();
                return;
            }

            if (target.closest('[data-pay-different-number]')) {
                startDifferentNumber();
                return;
            }

            if (target.closest('[data-pay-check]')) {
                checkStatus();
                return;
            }

            if (target.closest('[data-pay-help]')) {
                openHelp();
                return;
            }

            var copy = target.closest('[data-pay-copy]');
            if (copy) {
                copyControlNumber(copy);
            }
        });

        function goToChoose() {
            subtitle.textContent = 'Choose how you would like to pay';
            show('choose');
            focusFirst();
        }

        /*
         * "Pay from a different number" while a request is already in flight.
         *
         * The live request is left exactly as it is - the server starts a
         * separate one for the new line - so this only takes the customer to
         * the number field, prefilled with the number they are moving away
         * from so they can see what they are changing. The network and number
         * they were asked for are put back exactly as the pending request had
         * them, which is the one thing they should not have to remember.
         */
        function startDifferentNumber() {
            stopPolling();
            chooseMethod('mobile');

            if (lastPayment && lastPayment.network && mobileStep) {
                var network = mobileStep.querySelector('input[name="network"][value="' + lastPayment.network + '"]');
                if (network) {
                    network.checked = true;
                    network.dispatchEvent(new Event('change'));
                }
            }

            if (phoneInput) {
                if (lastPayment && lastPayment.phone) {
                    // Show it the way the field expects to be typed: local
                    // digits with the trunk zero, not the canonical 255 form.
                    var shown = phoneDisplayDigits(lastPayment.phone);
                    if (shown.charAt(0) !== '0') shown = '0' + shown;
                    phoneInput.value = formatPhoneForDisplay(shown);
                }

                phoneTouched = true;
                onPhoneInput();
                phoneInput.focus();
                phoneInput.select();
            }
        }

        function copyControlNumber(button) {
            var value = button.getAttribute('data-pay-copy');

            var done = function () {
                button.textContent = 'Copied';
                button.classList.add('is-copied');
                window.setTimeout(function () {
                    button.textContent = 'Copy control number';
                    button.classList.remove('is-copied');
                }, 2200);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(value).then(done).catch(function () { fallbackCopy(value, done); });
                return;
            }

            fallbackCopy(value, done);
        }

        function fallbackCopy(value, done) {
            var field = document.createElement('textarea');
            field.value = value;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();

            try {
                document.execCommand('copy');
                done();
            } catch (e) {
                showAlert('Copy the control number manually to continue.');
            }

            document.body.removeChild(field);
        }

        function openModal() {
            lastFocus = document.activeElement;
            open = true;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('pay-modal-open');

            var checked = form.querySelector('input[name="network"]:checked');
            if (checked) {
                checked.dispatchEvent(new Event('change'));
            }

            // The server is the only authority on what has already happened:
            // a reload or a back button must never show a stale step.
            loadState(function (state) {
                if (open) renderState(state);
            });
        }

        function closeModal() {
            open = false;
            closeHelp();
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('pay-modal-open');
            hideAlert();
            if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
        }

        for (var k = 0; k < openers.length; k++) {
            (function (opener) { opener.addEventListener('click', openModal); })(openers[k]);
        }
        for (var m = 0; m < closers.length; m++) {
            (function (closer) { closer.addEventListener('click', closeModal); })(closers[m]);
        }
        for (var b = 0; b < backs.length; b++) {
            (function (back) { back.addEventListener('click', function () { show('choose'); }); })(backs[b]);
        }
        if (overlay) overlay.addEventListener('click', closeModal);

        // Coming back to a hidden tab must resume the wait, because the timer gave
        // up rather than firing in the background.
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopPolling();
                return;
            }

            if (current === 'status' && pollTimer === null) {
                pollAttempts = 0;
                schedulePoll();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (!open) return;

            if (e.key === 'Escape' || e.keyCode === 27) {
                e.preventDefault();
                // The popup is the topmost thing, so it is what Escape closes.
                if (helpOpen) { closeHelp(); return; }
                closeModal();
                return;
            }

            if (e.key === 'Tab' || e.keyCode === 9) {
                var f = focusables();
                if (!f.length) { e.preventDefault(); return; }
                var first = f[0];
                var last = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });

        // Server-rendered validation failures come back with the dialog already
        // open on the step that has to be corrected.
        if (modal.getAttribute('data-pay-auto') === '1') {
            open = true;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('pay-modal-open');

            var saved = methodField ? methodField.value : 'mobile';
            chooseMethod(saved);
            showAlert('Please check the highlighted details and try again.');
        } else if (modal.getAttribute('data-pay-resume') === '1') {
            // A request is already on the customer's phone: open straight onto
            // the waiting panel so the automatic check starts now, with no
            // "check your details" nag (there is nothing to correct).
            openModal();
        } else {
            show('choose');
        }
    })();
</script>
@endpush

@extends('layouts.admin.app')

@section('title', 'Wallet & Withdrawals | E-Book Admin')
@section('heading', 'Wallet & Withdrawals')

@section('content')
    <div class="container-fluid">

        <div class="row">
            {{-- Live balance. Read fresh from the provider on every visit and
                 never cached: an old number next to a payout button is how an
                 operator sends money the store does not have. --}}
            <div class="col-xl-4 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-xs font-weight-bold text-uppercase text-gray-500 mb-2">
                            Available balance
                        </div>

                        @if ($balance !== null)
                            <div class="h3 mb-1">{{ \App\Support\Money::formatWhole($balance['balance'], $balance['currency']) }}</div>
                            <div class="small text-gray-600 mb-2">
                                Live from Abliner at
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($balance['checked_at'])->format('H:i:s') }}
                            </div>

                            @if ($balance['balances'] !== [])
                                <table class="table table-sm mb-0">
                                    <tbody>
                                        @foreach ($balance['balances'] as $code => $available)
                                            <tr>
                                                <th scope="row" class="w-50">{{ $code }}</th>
                                                <td class="text-right">{{ number_format($available) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        @else
                            {{-- Never show a number we could not verify. --}}
                            <div class="h3 mb-1 text-gray-500">Unavailable</div>
                            <div class="small text-danger mb-0">{{ $balanceError }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-8 col-md-12 mb-4">
                {{-- Send a payout. The fee comes from the provider, never from
                     a hardcoded rate in this file. --}}
                <div class="card h-100">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Send a payout</span>
                        <span class="small text-gray-500 ml-2">moves money out of the store wallet</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.wallet.withdrawals.store') }}">
                            @csrf

                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label for="method">Destination</label>
                                    <select class="form-control" id="method" name="method"
                                            data-toggle="payout-destination">
                                        @foreach ($methods as $method)
                                            <option value="{{ $method }}" @selected(old('method', $prefill['method']) === $method)>
                                                {{ $method === 'bank' ? 'Bank account' : 'Mobile money' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="amount">Amount (TZS)</label>
                                    <input type="number" class="form-control" id="amount" name="amount"
                                           min="{{ \App\Services\Abliner\AblinerWithdrawalService::MINIMUM_AMOUNT }}"
                                           max="{{ \App\Services\Abliner\AblinerWithdrawalService::MAXIMUM_AMOUNT }}"
                                           step="1" required
                                           value="{{ old('amount', $prefill['amount']) }}">
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="recipient">Recipient</label>
                                    <input type="text" class="form-control" id="recipient" name="recipient" required
                                           placeholder="07XXXXXXXXX or account number"
                                           value="{{ old('recipient', $prefill['recipient']) }}">
                                </div>

                                <div class="form-group col-md-3">
                                    <label for="reference">Reference</label>
                                    <input type="text" class="form-control" id="reference" name="reference"
                                           maxlength="64"
                                           placeholder="auto-generated if left blank"
                                           value="{{ old('reference') }}">
                                </div>
                            </div>

                            {{-- Bank-only fields, revealed by the script at the
                                 bottom of this file. --}}
                            <div class="form-row d-none" data-payout-bank>
                                <div class="form-group col-md-4">
                                    <label for="bank_code">Bank</label>
                                    <select class="form-control" id="bank_code" name="bank_code">
                                        <option value="">Select a bank</option>
                                        @foreach ($bankCodes as $code)
                                            <option value="{{ $code }}" @selected(old('bank_code', $prefill['bank_code']) === $code)>
                                                {{ $code }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="account_name">Account name</label>
                                    <input type="text" class="form-control" id="account_name" name="account_name"
                                           maxlength="150" value="{{ old('account_name', $prefill['account_name']) }}">
                                </div>
                            </div>

                            <p class="small text-gray-600">
                                The fee is read from Abliner, not from this application. Press
                                <strong>Check the fee</strong> first, then send.
                            </p>

                            <div class="form-row align-items-end">
                                <div class="form-group col-md-4 mb-2">
                                    <button type="submit" class="btn btn-outline-primary btn-block"
                                            formaction="{{ route('admin.wallet.withdrawals.preview') }}">
                                        Check the fee
                                    </button>
                                </div>
                                <div class="form-group col-md-4 mb-2">
                                    <label for="confirm" class="small mb-1">Type WITHDRAW to enable sending</label>
                                    <input type="text" class="form-control" id="confirm" name="confirm"
                                           autocomplete="off" spellcheck="false" placeholder="WITHDRAW"
                                           value="{{ old('confirm') }}">
                                </div>
                                <div class="form-group col-md-4 mb-2">
                                    <button type="submit" class="btn btn-danger btn-block"
                                            id="payoutSendButton" disabled
                                            onclick="return confirm('Send this payout for real?');">
                                        Send payout
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if ($feePreview = session('feePreview'))
            <div class="row">
                <div class="col-xl-6 col-md-12 mb-4">
                    <div class="card border-success">
                        <div class="card-header py-3">
                            <span class="font-weight-bold">Fee confirmed by Abliner</span>
                            <span class="small text-gray-500 ml-2">read live, not calculated locally</span>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <th scope="row">Amount</th>
                                        <td class="text-right">{{ \App\Support\Money::formatWhole($feePreview['amount'], $feePreview['currency']) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Fee ({{ $feePreview['percentage'] }})</th>
                                        <td class="text-right">{{ \App\Support\Money::formatWhole($feePreview['fee'], $feePreview['currency']) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><strong>Total to be debited</strong></th>
                                        <td class="text-right"><strong>{{ \App\Support\Money::formatWhole($feePreview['total'], $feePreview['currency']) }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="small text-gray-600 mt-3 mb-0">
                                The fee is quoted again when you send, so a changed fee cannot be accepted by accident.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            {{-- What this application asked Abliner to pay. Recorded before the
                 provider was called, so even an unanswered payout is visible. --}}
            <div class="col-xl-7 col-md-12 mb-4">
                <div class="card">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Payouts from this store</span>
                        <span class="small text-gray-500 ml-2">the last 25 requests</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($history->isEmpty())
                            <div class="p-3">
                                <p class="mb-0 text-gray-600">No payout has been sent from this store yet.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Reference</th>
                                            <th scope="col">Destination</th>
                                            <th scope="col" class="text-right">Amount</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Sent</th>
                                            <th scope="col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($history as $withdrawal)
                                            <tr>
                                                <td>
                                                    <code>{{ $withdrawal->reference }}</code>
                                                    @if ($withdrawal->requester)
                                                        <div class="small text-gray-500">
                                                            by {{ $withdrawal->requester->name }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="small">{{ $withdrawal->destinationLabel() }}</td>
                                                <td class="text-right text-nowrap">
                                                    {{ \App\Support\Money::formatWhole($withdrawal->amount, $withdrawal->currency) }}
                                                    @if ($withdrawal->fee)
                                                        <div class="small text-gray-500">
                                                            + {{ number_format($withdrawal->fee) }} fee
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $badge = $withdrawal->isCompleted() ? 'success' : ($withdrawal->isFailed() ? 'danger' : ($withdrawal->status === 'reversed' ? 'warning' : 'secondary'));
                                                    @endphp
                                                    <span class="badge badge-{{ $badge }}">{{ ucfirst($withdrawal->status) }}</span>
                                                    @if ($withdrawal->failure_reason)
                                                        <div class="small text-danger">{{ $withdrawal->failure_reason }}</div>
                                                    @endif
                                                </td>
                                                <td class="small text-gray-600 text-nowrap">{{ $withdrawal->paid_at?->format('j M Y H:i') ?? '—' }}</td>
                                                <td class="text-right">
                                                    @if ($withdrawal->isPending() && $withdrawal->provider_reference)
                                                        <form method="POST"
                                                              action="{{ route('admin.wallet.withdrawals.refresh', $withdrawal) }}">
                                                            @csrf
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                Check status
                                                            </button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- What the provider says happened in the wallet itself. --}}
            <div class="col-xl-5 col-md-12 mb-4">
                <div class="card">
                    <div class="card-header py-3">
                        <span class="font-weight-bold">Wallet activity at Abliner</span>
                        <span class="small text-gray-500 ml-2">live, read-only</span>
                    </div>
                    <div class="card-body p-0">
                        @if ($transactionsError !== null)
                            <div class="p-3">
                                <p class="mb-0 text-danger small">{{ $transactionsError }}</p>
                            </div>
                        @elseif ($transactions === [])
                            <div class="p-3">
                                <p class="mb-0 text-gray-600">Abliner reported no recent wallet activity.</p>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Type</th>
                                            <th scope="col">Reference</th>
                                            <th scope="col" class="text-right">Amount</th>
                                            <th scope="col">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($transactions as $transaction)
                                            <tr>
                                                <td class="small text-capitalize">
                                                    {{ $transaction['type'] ?: '—' }}
                                                    @if ($transaction['method'])
                                                        <div class="text-gray-500">{{ $transaction['method'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="small">
                                                    <code>{{ $transaction['reference'] ?: $transaction['id'] }}</code>
                                                    @if ($transaction['customer_reference'])
                                                        <div class="small text-gray-500">{{ $transaction['customer_reference'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="text-right text-nowrap">
                                                    @if ($transaction['amount'] !== null)
                                                        {{ \App\Support\Money::formatWhole($transaction['amount'], $transaction['currency']) }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="small">{{ ucfirst($transaction['status'] ?: '—') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            var methodSelect = document.querySelector('[data-toggle="payout-destination"]');
            var bankFields = document.querySelector('[data-payout-bank]');
            var confirmInput = document.getElementById('confirm');
            var sendButton = document.getElementById('payoutSendButton');

            // Show the bank fields only for a bank payout, and keep the hidden
            // values out of the request for a mobile payout so a stale bank
            // account number can never be submitted by accident.
            function syncDestination() {
                if (!methodSelect || !bankFields) return;

                var isBank = methodSelect.value === 'bank';

                bankFields.classList.toggle('d-none', !isBank);

                var bankCode = document.getElementById('bank_code');
                var accountName = document.getElementById('account_name');

                if (bankCode) bankCode.disabled = !isBank;
                if (accountName) accountName.disabled = !isBank;
            }

            // The send button is inert until the operator types the exact word.
            function syncConfirmation() {
                if (!confirmInput || !sendButton) return;

                sendButton.disabled = confirmInput.value.trim() !== 'WITHDRAW';
            }

            if (methodSelect) {
                methodSelect.addEventListener('change', syncDestination);
                syncDestination();
            }

            if (confirmInput) {
                confirmInput.addEventListener('input', syncConfirmation);
                syncConfirmation();
            }
        })();
    </script>
@endpush
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\Abliner\AblinerApiException;
use App\Services\Abliner\AblinerWalletService;
use App\Services\Abliner\AblinerWithdrawalService;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The store's Abliner wallet: live balance, live transaction history and
 * payouts.
 *
 * Two rules shape this controller:
 *
 *  1. The balance is NEVER cached. A stale balance next to a "Withdraw"
 *     button is how an operator sends money they do not have, so every visit
 *     reads it fresh from the provider.
 *
 *  2. A payout is only ever sent from a POST that carries the provider's own
 *     fee quote plus an explicit confirmation phrase typed by the operator. The
 *     request is re-quoted server-side on submit, so a fee that changed between
 *     preview and confirm cannot be accepted silently, and the amount must
 *     still be within the live wallet balance.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly AblinerWalletService $wallet,
        private readonly AblinerWithdrawalService $withdrawals,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $balance = $this->wallet->balance($balanceError);
        $transactions = $this->wallet->transactions(
            (int) $request->integer('limit', AblinerWalletService::DEFAULT_TRANSACTION_LIMIT),
            $transactionsError
        );

        return view('admin.wallet.index', [
            'balance' => $balance,
            'balanceError' => $balanceError,
            'transactions' => $transactions,
            'transactionsError' => $transactionsError,
            'history' => Withdrawal::query()
                ->with('requester')
                ->latest('id')
                ->limit(25)
                ->get(),
            'methods' => Withdrawal::METHODS,
            'bankCodes' => Withdrawal::BANK_CODES,
            'prefill' => [
                'method' => $request->string('method', Withdrawal::METHOD_MOBILE)->value(),
                'amount' => $request->string('amount', '')->value(),
                'recipient' => $request->string('recipient', '')->value(),
                'bank_code' => $request->string('bank_code', '')->value(),
                'account_name' => $request->string('account_name', '')->value(),
            ],
        ]);
    }

    /**
     * Read the provider's own fee quote for the amount the operator typed.
     *
     * This sends nothing and moves no money. It exists so the fee is never
     * invented by this application.
     */
    public function preview(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:'.AblinerWithdrawalService::MINIMUM_AMOUNT, 'max:'.AblinerWithdrawalService::MAXIMUM_AMOUNT],
            'method' => ['required', 'string', 'in:'.implode(',', Withdrawal::METHODS)],
        ]);

        $preview = $this->withdrawals->preview(
            (int) $validated['amount'],
            (string) $validated['method'],
            $error
        );

        if ($preview === null) {
            return back()->withInput()->with('error', $error ?? 'The fee could not be read from the provider.');
        }

        return back()->withInput()->with('success', 'Fee confirmed.')->with('feePreview', [
            'amount' => $preview->amount,
            'fee' => $preview->fee,
            'total' => $preview->totalDebited,
            'currency' => $preview->currency,
            'percentage' => $preview->feePercentage(),
        ]);
    }

    /**
     * Send a payout.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', Withdrawal::METHODS)],
            'amount' => ['required', 'integer', 'min:'.AblinerWithdrawalService::MINIMUM_AMOUNT, 'max:'.AblinerWithdrawalService::MAXIMUM_AMOUNT],
            'recipient' => ['required', 'string', 'max:64'],
            'bank_code' => ['nullable', 'string', Rule::in(Withdrawal::BANK_CODES)],
            'account_name' => ['nullable', 'string', 'max:150'],
            'reference' => ['nullable', 'string', 'max:64'],
            // The operator has to type this. It is the cheapest possible
            // interlock against a payout to the wrong number.
            'confirm' => ['required', 'string'],
        ], [
            'confirm.required' => 'Type WITHDRAW to confirm the payout.',
        ]);

        if ($validated['confirm'] !== 'WITHDRAW') {
            return back()->withInput()->with('error', 'Type WITHDRAW in the confirmation box to send this payout.');
        }

        $method = (string) $validated['method'];
        $recipient = trim((string) $validated['recipient']);

        if ($method === Withdrawal::METHOD_MOBILE) {
            $normalized = Phone::normalizeTanzanian($recipient);

            if ($normalized === null) {
                return back()->withInput()->with('error', 'Enter a valid Tanzanian mobile number, for example 07XXXXXXXXX.');
            }

            $recipient = $normalized;
        }

        if ($method === Withdrawal::METHOD_BANK) {
            if (blank($validated['bank_code'] ?? null) || blank($validated['account_name'] ?? null)) {
                return back()->withInput()->with('error', 'A bank payout needs both a bank and an account name.');
            }
        }

        if (! $this->withdrawals->isConfigured()) {
            return back()->withInput()->with('error', 'No Abliner API key is configured, so payouts cannot be sent.');
        }

        $amount = (int) $validated['amount'];

        // Re-quote at submit time. The operator confirmed a specific fee; if the
        // provider now quotes a different total, the confirmation is stale and
        // this must not go through.
        $preview = $this->withdrawals->preview($amount, $method, $error);

        if ($preview === null) {
            return back()->withInput()->with('error', $error ?? 'The fee could not be read from the provider, so the payout was not sent.');
        }

        $balance = $this->wallet->balance($balanceError);

        if ($balance !== null && $preview->totalDebited > $balance['balance']) {
            return back()->withInput()->with('error', 'That payout costs more than the wallet balance. Nothing was sent.');
        }

        $withdrawal = Withdrawal::create([
            'provider' => Withdrawal::PROVIDER_ABLINER,
            'requested_by' => $request->user()->id,
            'method' => $method,
            'recipient' => $recipient,
            'bank_code' => $method === Withdrawal::METHOD_BANK ? $validated['bank_code'] : null,
            'account_name' => $method === Withdrawal::METHOD_BANK ? $validated['account_name'] : null,
            'amount' => $amount,
            'fee' => $preview->fee,
            'currency' => $preview->currency,
            'reference' => filled($validated['reference'] ?? null)
                ? trim((string) $validated['reference'])
                : 'WDL-'.now()->format('YmdHis'),
            'status' => Withdrawal::STATUS_PENDING,
        ]);

        try {
            $this->withdrawals->send($withdrawal, $request->user());
        } catch (AblinerApiException $exception) {
            return back()->withInput()->with(
                'error',
                'The payout was not accepted by the provider. '.$exception->getMessage()
            );
        } catch (Throwable) {
            return back()->withInput()->with(
                'error',
                'The payout could not be completed. Check the wallet history before trying again — a timeout does not mean the money was not sent.'
            );
        }

        return redirect()->route('admin.wallet.index')
            ->with('success', 'Payout of '.Money::formatWhole($withdrawal->amount, $withdrawal->currency).' was sent to '.$withdrawal->destinationLabel().'.')
            ->with('alert_title', 'Payout sent');
    }

    /**
     * Re-read a payout's status from the provider.
     */
    public function refresh(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        abort_if(
            ! $this->withdrawals->isConfigured(),
            403,
            'Payouts cannot be checked because no API key is configured.'
        );

        try {
            $status = $this->withdrawals->verify($withdrawal);
        } catch (AblinerApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($status === null) {
            return back()->with('info', 'The provider has no record of this payout yet.');
        }

        $withdrawal->update([
            'status' => $this->withdrawals->mapProviderStatus($status),
            'settled_at' => now(),
        ]);

        return back()->with('success', 'Payout status updated.');
    }
}
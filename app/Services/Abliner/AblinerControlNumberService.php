<?php

namespace App\Services\Abliner;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;

/**
 * ClickPesa BillPay control numbers, via POST /api/v1/control-numbers.
 *
 * A control number lets the customer pay from ANY mobile money app or bank
 * instead of accepting a USSD push to one number. The provider creates a
 * one-time control number for the exact amount and the same webhook that fires
 * for deposits fires when the customer pays it, so the reconciliation path is
 * identical to a mobile money collection.
 */
class AblinerControlNumberService
{
    public const METHOD_CONTROL_NUMBER = 'control_number';

    public const PAYMENT_MODE_EXACT = 'EXACT';

    public const PAYMENT_MODE_PARTIAL = 'PARTIAL';

    /**
     * The published ClickPesa BillPay dial sequences, per channel.
     *
     * These are shown verbatim to the customer after they generate a control
     * number, because the whole point of the channel is that they pay from
     * their own phone or from a bank branch/ATM rather than accepting a push.
     * `[control number]` is replaced at render time.
     *
     * @var array<string, array{label: string, code: string, steps: list<string>}>
     */
    public const DIAL_INSTRUCTIONS = [
        'airtel_money' => [
            'label' => 'Airtel Money',
            'code' => '*150*60#',
            'steps' => [
                'Choose 6 - Financial Services',
                'Choose 6 - SACCOS and MFI',
                'Choose 1 - MFI',
                'Choose 5 - A-G',
                'Choose 8 - ClickPesa',
                'Enter reference number [control number]',
                'Enter the amount',
                'Choose 1 to confirm',
                'Enter your PIN',
            ],
        ],
        'mixx_yas' => [
            'label' => 'Mixx by Yas',
            'code' => '*150*88#',
            'steps' => [
                'Choose 4 - Pay Bills',
                'Choose 3 - Enter Business Number',
                'Enter business number 889999',
                'Enter reference [control number]',
                'Enter the amount',
                'Enter your PIN',
            ],
        ],
        'halotel' => [
            'label' => 'Halotel',
            'code' => '*150*01#',
            'steps' => [
                'Choose 4 - Pay Bills',
                'Choose 3 - Enter Business Number',
                'Enter business number 889999',
                'Enter reference [control number]',
                'Enter the amount',
                'Enter your PIN',
                'Choose 1 to confirm',
            ],
        ],
        'mpesa' => [
            'label' => 'M-Pesa',
            'code' => '*150*01#',
            'steps' => [
                'Choose 1 - SimBanking and enter your PIN',
                'Choose 4 - Pay Bills',
                'Choose 6 - Institutions',
                'Choose 7 - Others',
                'Choose N (Next) until you see CLICKPESA',
                'Choose CLICKPESA and enter [control number]',
                'Enter the amount and confirm',
            ],
        ],
        'bank' => [
            'label' => 'Bank',
            'code' => '*150*03#',
            'steps' => [
                'Choose 1 - SimBanking and enter your PIN',
                'Choose 4 - Pay Bills',
                'Choose 6 - Institutions',
                'Choose 7 - Others',
                'Choose N (Next) until you see CLICKPESA',
                'Choose CLICKPESA and enter [control number]',
                'Enter the amount and confirm',
            ],
        ],
    ];

    public function __construct(
        private readonly AblinerApiClient $http,
        private readonly AblinerPaymentService $payments,
    ) {}

    /**
     * The dial sequences for a network the customer picked, plus the bank flow
     * which always applies.
     *
     * @return list<array{label: string, code: string, steps: list<string>}>
     */
    public function instructionsFor(?string $network = null): array
    {
        $channels = [];

        if ($network !== null && isset(self::DIAL_INSTRUCTIONS[$network])) {
            $channels[] = self::DIAL_INSTRUCTIONS[$network];
        }

        $channels[] = self::DIAL_INSTRUCTIONS['bank'];

        return $channels;
    }

    /**
     * Create a one-time control number for an order's exact amount.
     *
     * The local payment row must already exist so the row id can be reused as
     * the idempotency key. On success the row is updated with the transaction
     * id and the control number itself.
     *
     * @throws AblinerApiException
     */
    public function createControlNumber(Payment $payment, User $customer, string $network): Payment
    {
        if (! $this->payments->isConfigured()) {
            throw new AblinerApiException('Payment provider is not configured.', 0, [], false);
        }

        $order = $payment->order()->firstOrFail();
        $amount = $this->payments->amountFor($order);
        $idempotencyKey = $this->payments->idempotencyKeyFor($payment);

        $payload = [
            'amount' => $amount,
            'description' => 'Order '.$order->order_number,
            // EXACT: the customer must pay the precise amount, otherwise the
            // provider will not credit the transaction.
            'payment_mode' => self::PAYMENT_MODE_EXACT,
            'reference' => (string) $order->order_number,
        ];

        if (filled($customer->name)) {
            $payload['customer_name'] = (string) $customer->name;
        }

        [$success, $statusCode, $body] = $this->http->post('/control-numbers', $payload, $idempotencyKey);

        if (! $success) {
            throw $this->http->exception($statusCode, $body);
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $controlNumber = (string) ($data['control_number'] ?? '');

        if ($controlNumber === '') {
            throw $this->http->exception($statusCode === 200 ? 502 : $statusCode, [
                'code' => 'unexpected_response',
                'message' => 'Abliner did not return a control number.',
            ]);
        }

        $payment->update([
            'provider' => Payment::PROVIDER_ABLINER,
            'payment_type' => self::METHOD_CONTROL_NUMBER,
            'provider_reference' => $data['id'] ?? $payment->provider_reference,
            'external_reference' => $controlNumber,
            'customer_reference' => (string) $order->order_number,
            'amount' => (int) ($data['amount'] ?? $amount),
            'currency' => AblinerPaymentService::CURRENCY,
            'status' => Payment::STATUS_PENDING,
            'channel_provider' => $network,
            'control_number' => $controlNumber,
            'provider_payload' => $body,
        ]);

        $payment->refresh();

        return $payment;
    }

    /**
     * The amount a control number must be paid with, formatted for display.
     */
    public function amountLabel(Payment $payment): string
    {
        return Money::formatWhole((int) $payment->amount, (string) $payment->currency);
    }

    /**
     * Does this payment use the control-number channel?
     */
    public function handles(Payment $payment): bool
    {
        return $payment->payment_type === self::METHOD_CONTROL_NUMBER;
    }

    /**
     * An order whose amount is outside what the provider accepts cannot get a
     * control number either.
     */
    public function isAmountInRange(Order $order): bool
    {
        return $this->payments->isAmountInRange($this->payments->amountFor($order));
    }
}
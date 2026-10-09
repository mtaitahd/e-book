<?php

namespace App\Settings;

use App\Models\Payment;
use App\Services\Abliner\AblinerConnectionTestResult;
use App\Services\Abliner\AblinerPaymentService;
use App\Services\Abliner\AblinerSignatureVerifier;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read model for the admin Payment Settings page.
 *
 * This service is the single place where "is the payment integration healthy"
 * is decided. It never writes configuration, never persists a secret and never
 * mutates an order, a payment or a webhook event.
 */
class PaymentSettingsService
{
    public function __construct(
        private readonly AblinerPaymentService $payments,
        private readonly AblinerSignatureVerifier $verifier,
        private readonly PaymentProviderConfig $config,
    ) {}

    public function status(): PaymentProviderStatus
    {
        return PaymentProviderStatus::fromEnvironment($this->payments, $this->config);
    }

    /**
     * Real activity from the payments table, or real zeros when the store has
     * genuinely never taken a payment.
     */
    public function activity(): PaymentActivitySummary
    {
        $counts = DB::table('payments')
            ->where('provider', Payment::PROVIDER_ABLINER)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $lastPayment = DB::table('payments')
            ->where('provider', Payment::PROVIDER_ABLINER)
            ->max('created_at');

        $lastCompleted = DB::table('payments')
            ->where('provider', Payment::PROVIDER_ABLINER)
            ->where('status', Payment::STATUS_COMPLETED)
            ->max('paid_at') ?? DB::table('payments')
            ->where('provider', Payment::PROVIDER_ABLINER)
            ->where('status', Payment::STATUS_COMPLETED)
            ->max('created_at');

        return PaymentActivitySummary::fromCounts($counts, $lastPayment, $lastCompleted);
    }

    /**
     * Webhook security posture, derived rather than trusted.
     */
    public function webhookPosture(): WebhookPosture
    {
        return new WebhookPosture(
            secretPresent: $this->verifier->isConfigured(),
            verifyOnWebhook: $this->config->verifyOnWebhook(),
            // The endpoint the provider actually posts to, resolved from the
            // route so the page can never drift from the real route name.
            endpoint: route('webhooks.abliner'),
            toleranceSeconds: AblinerSignatureVerifier::FRESHNESS_SECONDS,
        );
    }

    /**
     * Run the safe, read-only connectivity check.
     *
     * Every failure mode is converted into a safe, pre-written message here so
     * that no exception message, provider payload or credential can reach the
     * browser by accident.
     */
    public function testConnection(): AblinerConnectionTestResult
    {
        try {
            return $this->payments->testConnection();
        } catch (Throwable) {
            // Belt and braces. testConnection() already handles its own errors;
            // if anything ever escapes it, report nothing specific rather than
            // forwarding an internal message to an admin-facing page.
            return AblinerConnectionTestResult::unexpected(null, null);
        }
    }
}
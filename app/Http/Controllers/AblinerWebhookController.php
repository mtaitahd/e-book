<?php

namespace App\Http\Controllers;

use App\Services\Abliner\AblinerSignatureVerifier;
use App\Services\Abliner\AblinerWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Abliner payment and payout callbacks.
 *
 * This is the ONLY route that is exempt from CSRF validation, and that is
 * intentional: Abliner does not hold a session cookie, so authenticity is
 * established with the shared webhook secret + HMAC signature instead.
 * CSRF protection remains active everywhere else in the application.
 *
 * Abliner retries a delivery up to twice when it does not get a 2xx within 8
 * seconds, and the payload `id` is stable across those retries, so answering
 * 200 for anything we have already recorded is the correct answer, not a
 * silent failure.
 */
class AblinerWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        AblinerSignatureVerifier $verifier,
        AblinerWebhookService $webhooks,
    ): JsonResponse {
        if (! $verifier->isConfigured()) {
            return response()->json(['status' => 'ignored', 'reason' => 'webhook secret not configured']);
        }

        $timestamp = $request->header(AblinerSignatureVerifier::HEADER_TIMESTAMP);
        $signature = $request->header(AblinerSignatureVerifier::HEADER_SIGNATURE);
        $rawBody = $request->getContent();

        if (! $verifier->verify($timestamp, $signature, $rawBody)) {
            return response()->json(['status' => 'rejected', 'reason' => 'invalid signature']);
        }

        $event = $webhooks->handle($rawBody, (array) $request->json()->all());

        return response()->json([
            'status' => $event->status,
            'event_id' => $event->event_id,
        ]);
    }
}
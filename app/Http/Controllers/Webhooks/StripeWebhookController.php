<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IMP-009 — Stripe webhook receipt (docs/implementation/
 * IMP-009-payment-hub.md "Webhook Security" / "Routes / API
 * Boundary": POST /webhooks/payments/stripe). See
 * TripayWebhookController for the shared contract. Stripe's own
 * retry-on-non-2xx behavior is respected: permanent rejections
 * return 400 (no retry), transient pre-verification failures would
 * surface as 500s (retry).
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request, PaymentWebhookHandler $handler): JsonResponse
    {
        $result = $handler->handle('stripe', $request);

        return response()->json(['status' => $result['outcome']], $result['http_status']);
    }
}

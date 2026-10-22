<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IMP-009 — Tripay callback receipt (docs/implementation/
 * IMP-009-payment-hub.md "Webhook Security" / "Routes / API
 * Boundary": POST /webhooks/payments/tripay). One route per provider
 * — never a shared generic endpoint inferring the provider from
 * payload shape. No business logic lives here — all delegation to
 * PaymentWebhookHandler (verify-before-mutate pipeline). The response
 * is a minimal generic acknowledgment — never sensitive diagnostics.
 */
class TripayWebhookController extends Controller
{
    public function handle(Request $request, PaymentWebhookHandler $handler): JsonResponse
    {
        $result = $handler->handle('tripay', $request);

        return response()->json(['status' => $result['outcome']], $result['http_status']);
    }
}

<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IMP-009 — Xendit webhook receipt (docs/implementation/
 * IMP-009-payment-hub.md "Webhook Security" / "Routes / API
 * Boundary": POST /webhooks/payments/xendit). See
 * TripayWebhookController for the shared contract.
 */
class XenditWebhookController extends Controller
{
    public function handle(Request $request, PaymentWebhookHandler $handler): JsonResponse
    {
        $result = $handler->handle('xendit', $request);

        return response()->json(['status' => $result['outcome']], $result['http_status']);
    }
}

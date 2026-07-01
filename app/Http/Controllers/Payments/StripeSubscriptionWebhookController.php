<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Payments\PaymentService;
use App\Services\Saas\StripeSaasBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeSubscriptionWebhookController extends Controller
{
    public function store(Request $request, StripeSaasBillingService $billing, AuditLogService $auditLog): JsonResponse
    {
        $rawPayload = $request->getContent();

        if (! $billing->verifyWebhookSignature($rawPayload, $request->header('Stripe-Signature'))) {
            return response()->json(['message' => 'Invalid Stripe webhook signature.'], 400);
        }

        $event = json_decode($rawPayload, true) ?: [];
        $subscription = $billing->syncWebhook($event);

        app(PaymentService::class)->storeWebhookEvent(
            'stripe_billing',
            $event,
            $request->headers->all(),
            $subscription?->tenant,
            null,
            true
        );

        if ($subscription) {
            $auditLog->subscriptionChanged(AuditLogService::SUBSCRIPTION_WEBHOOK_SYNCED, $subscription->tenant, [
                'event_id' => $event['id'] ?? null,
                'event_type' => $event['type'] ?? null,
                'status' => $subscription->status,
            ]);
        }

        return response()->json(['received' => true]);
    }
}

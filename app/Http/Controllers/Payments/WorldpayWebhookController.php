<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\ProviderAccountSetting;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorldpayWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();
        $setting = $this->providerSetting($payload);
        $signatureVerified = $this->signatureVerified($request, $setting);

        app(PaymentService::class)->storeWebhookEvent(
            'worldpay',
            $payload,
            $request->headers->all(),
            $setting?->tenant,
            $setting?->branch_id,
            $signatureVerified
        );

        if (! $signatureVerified) {
            return response()->json(['message' => 'Invalid Worldpay webhook signature.'], 400);
        }

        return response()->json(['received' => true]);
    }

    private function providerSetting(array $payload): ?ProviderAccountSetting
    {
        $tenantId = $payload['tenant_id'] ?? null;
        $accountReference = $payload['account_reference'] ?? $payload['merchant']['entity'] ?? null;

        return ProviderAccountSetting::query()
            ->where('provider', 'worldpay')
            ->where('is_active', true)
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($accountReference, fn ($query) => $query->where('account_reference', $accountReference))
            ->oldest('branch_id')
            ->first();
    }

    private function signatureVerified(Request $request, ?ProviderAccountSetting $setting): bool
    {
        $secret = ($setting?->encrypted_credentials ?? [])['webhook_secret'] ?? null;
        $signature = $request->header('Event-Signature') ?? $request->header('X-Worldpay-Signature');

        if (! $secret || ! $signature) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}

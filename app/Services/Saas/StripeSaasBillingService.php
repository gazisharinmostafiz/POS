<?php

namespace App\Services\Saas;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\VendorSubscription;
use App\Services\Payments\PaymentService;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class StripeSaasBillingService
{
    public const INTERVAL_MONTHLY = 'monthly';
    public const INTERVAL_YEARLY = 'yearly';

    public function createCustomer(Tenant $tenant): string
    {
        if ($tenant->stripe_customer_id) {
            return $tenant->stripe_customer_id;
        }

        $response = $this->stripe('post', '/v1/customers', [
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ]);

        $this->ensureSuccess($response, 'Unable to create Stripe customer.');

        $customerId = $response->json('id');
        $tenant->forceFill(['stripe_customer_id' => $customerId])->save();

        return $customerId;
    }

    public function createSubscription(Tenant $tenant, Plan $plan, string $interval = self::INTERVAL_MONTHLY, int $trialDays = 0): VendorSubscription
    {
        $customerId = $this->createCustomer($tenant);
        $priceId = $this->priceId($plan, $interval);

        $payload = [
            'customer' => $customerId,
            'items' => [
                ['price' => $priceId],
            ],
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_interval' => $interval,
            ],
            'payment_behavior' => 'default_incomplete',
            'expand' => ['latest_invoice.payment_intent'],
        ];

        if ($trialDays > 0) {
            $payload['trial_period_days'] = $trialDays;
        }

        $response = $this->stripe('post', '/v1/subscriptions', $payload);
        $this->ensureSuccess($response, 'Unable to create Stripe subscription.');

        return $this->syncSubscriptionPayload($response->json(), $tenant, $plan, $interval);
    }

    public function changePlan(Tenant $tenant, Plan $plan, string $interval = self::INTERVAL_MONTHLY): VendorSubscription
    {
        $subscription = $tenant->vendorSubscription()->firstOrFail();
        $priceId = $this->priceId($plan, $interval);
        $payload = [
            'items' => [
                [
                    'id' => $subscription->stripe_subscription_item_id,
                    'price' => $priceId,
                ],
            ],
            'metadata' => [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_interval' => $interval,
            ],
            'proration_behavior' => 'create_prorations',
        ];

        $response = $this->stripe('post', '/v1/subscriptions/'.$subscription->stripe_subscription_id, $payload);
        $this->ensureSuccess($response, 'Unable to update Stripe subscription.');

        return $this->syncSubscriptionPayload($response->json(), $tenant, $plan, $interval);
    }

    public function cancel(Tenant $tenant): VendorSubscription
    {
        $subscription = $tenant->vendorSubscription()->firstOrFail();
        $response = $this->stripe('delete', '/v1/subscriptions/'.$subscription->stripe_subscription_id);
        $this->ensureSuccess($response, 'Unable to cancel Stripe subscription.');

        return $this->syncSubscriptionPayload($response->json(), $tenant, $subscription->plan, $subscription->billing_interval);
    }

    public function syncSubscriptionPayload(array $payload, ?Tenant $tenant = null, ?Plan $plan = null, ?string $interval = null): VendorSubscription
    {
        $tenant ??= $this->tenantFromPayload($payload);
        $plan ??= $this->planFromPayload($payload);
        $interval ??= Arr::get($payload, 'metadata.billing_interval') ?: $this->intervalFromPayload($payload);
        $status = $payload['status'] ?? 'incomplete';
        $trialEndsAt = $this->time($payload['trial_end'] ?? null);
        $currentPeriodEndsAt = $this->time($payload['current_period_end'] ?? null);
        $cancelledAt = $this->time($payload['canceled_at'] ?? null);
        $graceEndsAt = $status === 'past_due'
            ? now()->addDays((int) config('services.stripe.subscription_grace_days', 7))
            : null;

        $subscription = VendorSubscription::query()->updateOrCreate(
            ['provider' => 'stripe', 'stripe_subscription_id' => $payload['id']],
            [
                'tenant_id' => $tenant->id,
                'plan_id' => $plan?->id,
                'billing_interval' => $interval ?: self::INTERVAL_MONTHLY,
                'stripe_customer_id' => $payload['customer'] ?? $tenant->stripe_customer_id,
                'stripe_subscription_item_id' => Arr::get($payload, 'items.data.0.id'),
                'stripe_price_id' => Arr::get($payload, 'items.data.0.price.id'),
                'status' => $status,
                'trial_ends_at' => $trialEndsAt,
                'current_period_ends_at' => $currentPeriodEndsAt,
                'grace_ends_at' => $graceEndsAt,
                'cancelled_at' => $cancelledAt,
                'provider_metadata' => app(PaymentService::class)->safeMetadata([
                    'latest_invoice' => $payload['latest_invoice'] ?? null,
                    'cancel_at_period_end' => $payload['cancel_at_period_end'] ?? false,
                ]),
            ]
        );

        $tenant->forceFill([
            'plan_id' => $plan?->id,
            'stripe_customer_id' => $payload['customer'] ?? $tenant->stripe_customer_id,
            'subscription_status' => $this->tenantStatus($status),
            'subscription_ends_at' => $this->tenantAccessEndsAt($status, $trialEndsAt, $currentPeriodEndsAt, $graceEndsAt),
            'is_active' => ! in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true),
        ])->save();

        return $subscription->fresh(['tenant', 'plan']);
    }

    public function syncWebhook(array $event): ?VendorSubscription
    {
        $type = $event['type'] ?? '';
        $object = Arr::get($event, 'data.object', []);

        if (str_starts_with($type, 'customer.subscription.')) {
            return $this->syncSubscriptionPayload($object);
        }

        if ($type === 'invoice.payment_failed') {
            $subscription = VendorSubscription::query()
                ->where('stripe_subscription_id', $object['subscription'] ?? null)
                ->first();

            if ($subscription) {
                $subscription->forceFill([
                    'status' => 'past_due',
                    'grace_ends_at' => now()->addDays((int) config('services.stripe.subscription_grace_days', 7)),
                ])->save();

                $subscription->tenant->forceFill([
                    'subscription_status' => 'past_due',
                    'subscription_ends_at' => $subscription->grace_ends_at,
                ])->save();
            }

            return $subscription;
        }

        if ($type === 'invoice.paid') {
            $subscription = VendorSubscription::query()
                ->where('stripe_subscription_id', $object['subscription'] ?? null)
                ->first();

            if ($subscription) {
                $subscription->forceFill(['status' => 'active', 'grace_ends_at' => null])->save();
                $subscription->tenant->forceFill(['subscription_status' => 'active'])->save();
            }

            return $subscription;
        }

        return null;
    }

    public function isTenantRestricted(Tenant $tenant): bool
    {
        return ! $tenant->is_active || ! $tenant->hasActiveSubscription();
    }

    public function verifyWebhookSignature(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.stripe.webhook_secret');

        if (! $secret || ! $signatureHeader) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part) {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return [$key => $value];
            });
        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature || abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function stripe(string $method, string $path, array $payload = []): Response
    {
        $secret = config('services.stripe.secret');

        if (! $secret) {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        $client = Http::asForm()
            ->acceptJson()
            ->withToken($secret)
            ->timeout(15);

        $baseUrl = rtrim(config('services.stripe.base_url', 'https://api.stripe.com'), '/');

        return $client->{$method}($baseUrl.$path, $payload);
    }

    private function ensureSuccess(Response $response, string $fallback): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException(
                $response->json('error.message') ?? $response->json('message') ?? $fallback
            );
        }
    }

    private function priceId(Plan $plan, string $interval): string
    {
        $priceId = $interval === self::INTERVAL_YEARLY
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_monthly_price_id;

        if (! $priceId) {
            throw new \InvalidArgumentException("Stripe {$interval} price is not configured for plan [{$plan->name}].");
        }

        return $priceId;
    }

    private function tenantFromPayload(array $payload): Tenant
    {
        $tenantId = Arr::get($payload, 'metadata.tenant_id');
        $customerId = $payload['customer'] ?? null;

        return Tenant::query()
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId))
            ->when(! $tenantId && $customerId, fn ($query) => $query->where('stripe_customer_id', $customerId))
            ->firstOrFail();
    }

    private function planFromPayload(array $payload): ?Plan
    {
        $planId = Arr::get($payload, 'metadata.plan_id');
        $priceId = Arr::get($payload, 'items.data.0.price.id');

        return Plan::query()
            ->when($planId, fn ($query) => $query->whereKey($planId))
            ->when(! $planId && $priceId, function ($query) use ($priceId) {
                $query->where('stripe_monthly_price_id', $priceId)
                    ->orWhere('stripe_yearly_price_id', $priceId);
            })
            ->first();
    }

    private function intervalFromPayload(array $payload): string
    {
        $priceId = Arr::get($payload, 'items.data.0.price.id');
        $plan = $priceId ? $this->planFromPayload($payload) : null;

        return $plan?->stripe_yearly_price_id === $priceId ? self::INTERVAL_YEARLY : self::INTERVAL_MONTHLY;
    }

    private function tenantStatus(string $status): string
    {
        return match ($status) {
            'active', 'trialing', 'past_due' => $status,
            'canceled', 'unpaid', 'incomplete_expired' => 'disabled',
            default => 'pending',
        };
    }

    private function tenantAccessEndsAt(string $status, ?Carbon $trialEndsAt, ?Carbon $currentPeriodEndsAt, ?Carbon $graceEndsAt): ?Carbon
    {
        return match ($status) {
            'trialing' => $trialEndsAt,
            'past_due' => $graceEndsAt,
            'active' => $currentPeriodEndsAt,
            default => now(),
        };
    }

    private function time($timestamp): ?Carbon
    {
        return $timestamp ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }
}

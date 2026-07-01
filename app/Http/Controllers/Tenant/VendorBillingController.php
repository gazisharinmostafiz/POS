<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\AuditLogService;
use App\Services\Saas\StripeSaasBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorBillingController extends Controller
{
    public function show(): View
    {
        return view('tenant.billing.show', [
            'tenant' => current_tenant()->load(['plan', 'vendorSubscription.plan']),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'subscription' => current_tenant()->vendorSubscription()->with('plan')->first(),
        ]);
    }

    public function subscribe(Request $request, StripeSaasBillingService $billing, AuditLogService $auditLog): RedirectResponse
    {
        $payload = $this->validatedPayload($request);
        $plan = Plan::query()->whereKey($payload['plan_id'])->where('is_active', true)->firstOrFail();

        try {
            $subscription = $billing->createSubscription(current_tenant(), $plan, $payload['billing_interval'], (int) ($payload['trial_days'] ?? 0));
        } catch (\Throwable $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()])->withInput();
        }

        $auditLog->subscriptionChanged(AuditLogService::SUBSCRIPTION_CREATED, current_tenant(), [
            'plan_id' => $plan->id,
            'billing_interval' => $subscription->billing_interval,
            'status' => $subscription->status,
        ]);

        return back()->with('status', 'Subscription created.');
    }

    public function changePlan(Request $request, StripeSaasBillingService $billing, AuditLogService $auditLog): RedirectResponse
    {
        $payload = $this->validatedPayload($request, false);
        $plan = Plan::query()->whereKey($payload['plan_id'])->where('is_active', true)->firstOrFail();

        try {
            $subscription = $billing->changePlan(current_tenant(), $plan, $payload['billing_interval']);
        } catch (\Throwable $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()])->withInput();
        }

        $auditLog->subscriptionChanged(AuditLogService::SUBSCRIPTION_CHANGED, current_tenant(), [
            'plan_id' => $plan->id,
            'billing_interval' => $subscription->billing_interval,
            'status' => $subscription->status,
        ]);

        return back()->with('status', 'Subscription plan updated.');
    }

    public function cancel(StripeSaasBillingService $billing, AuditLogService $auditLog): RedirectResponse
    {
        try {
            $subscription = $billing->cancel(current_tenant());
        } catch (\Throwable $exception) {
            return back()->withErrors(['billing' => $exception->getMessage()]);
        }

        $auditLog->subscriptionChanged(AuditLogService::SUBSCRIPTION_CANCELLED, current_tenant(), [
            'subscription_id' => $subscription->stripe_subscription_id,
            'status' => $subscription->status,
        ]);

        return back()->with('status', 'Subscription cancelled.');
    }

    private function validatedPayload(Request $request, bool $withTrial = true): array
    {
        return $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
            'trial_days' => [$withTrial ? 'nullable' : 'prohibited', 'integer', 'min:0', 'max:365'],
        ]);
    }
}

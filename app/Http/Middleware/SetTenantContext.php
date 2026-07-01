<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;
use App\Services\Saas\StripeSaasBillingService;
use Closure;
use Illuminate\Http\Request;

class SetTenantContext
{
    public function __construct(
        private TenantContext $context,
        private TenantResolver $resolver,
        private StripeSaasBillingService $billing
    ) {
    }

    public function handle(Request $request, Closure $next, bool $required = false)
    {
        $this->context->clear();

        $tenant = $this->resolver->resolveTenant($request) ?? $request->user()?->tenant;

        if (! $tenant && $required) {
            abort(404, 'Tenant context is required.');
        }

        if ($tenant) {
            $this->context->setTenant($tenant);
            $this->context->setBranch(
                $this->resolver->resolveBranch($request, $tenant) ?? $request->user()?->branch
            );

            if ($required && $this->billing->isTenantRestricted($tenant) && ! $request->is('tenant/billing*')) {
                abort(402, 'Vendor subscription requires attention.');
            }
        }

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}

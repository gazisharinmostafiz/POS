<?php

use App\Models\Branch;
use App\Models\Tenant;
use App\Services\FeatureGate;
use App\Services\Tenancy\TenantContext;

if (! function_exists('current_tenant')) {
    function current_tenant(): ?Tenant
    {
        return app(TenantContext::class)->tenant();
    }
}

if (! function_exists('current_branch')) {
    function current_branch(): ?Branch
    {
        return app(TenantContext::class)->branch();
    }
}

if (! function_exists('tenantHasFeature')) {
    function tenantHasFeature(string $featureKey, ?Tenant $tenant = null): bool
    {
        return app(FeatureGate::class)->tenantHasFeature($tenant ?? current_tenant(), $featureKey);
    }
}

if (! function_exists('tenantFeatureWithinLimit')) {
    function tenantFeatureWithinLimit(string $featureKey, int $currentUsage, int $increment = 1, ?Tenant $tenant = null): bool
    {
        return app(FeatureGate::class)->withinLimit($tenant ?? current_tenant(), $featureKey, $currentUsage, $increment);
    }
}

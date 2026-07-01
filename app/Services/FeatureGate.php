<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\Tenant;

class FeatureGate
{
    public function tenantHasFeature(?Tenant $tenant, string $featureKey): bool
    {
        if (! $tenant) {
            return $this->enabledByGlobalFlag($featureKey);
        }

        if (! $tenant->hasActiveSubscription()) {
            return false;
        }

        $tenantFlag = $this->tenantFlag($tenant, $featureKey);

        if ($tenantFlag) {
            return $tenantFlag->enabled;
        }

        if ($this->planFeatureEnabled($tenant, $featureKey)) {
            return true;
        }

        return $this->addonFeatureEnabled($tenant, $featureKey);
    }

    public function limitFor(?Tenant $tenant, string $featureKey): ?int
    {
        if (! $tenant) {
            return $this->globalFlag($featureKey)?->limit;
        }

        if (! $tenant->hasActiveSubscription()) {
            return 0;
        }

        $tenantFlag = $this->tenantFlag($tenant, $featureKey);

        if ($tenantFlag) {
            return $tenantFlag->enabled ? $tenantFlag->limit : 0;
        }

        $limits = [];

        $planFeature = $tenant->plan?->features()
            ->where('feature_key', $featureKey)
            ->where('enabled', true)
            ->first();

        if ($planFeature && $planFeature->limit !== null) {
            $limits[] = $planFeature->limit;
        }

        foreach ($this->activeAddonFeatures($tenant, $featureKey) as $addonFeature) {
            if ($addonFeature->limit !== null) {
                $limits[] = $addonFeature->limit;
            }
        }

        return $limits === [] ? null : max($limits);
    }

    public function withinLimit(?Tenant $tenant, string $featureKey, int $currentUsage, int $increment = 1): bool
    {
        if (! $this->tenantHasFeature($tenant, $featureKey)) {
            return false;
        }

        $limit = $this->limitFor($tenant, $featureKey);

        return $limit === null || ($currentUsage + $increment) <= $limit;
    }

    private function planFeatureEnabled(Tenant $tenant, string $featureKey): bool
    {
        return (bool) $tenant->plan?->features()
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->where('feature_key', $featureKey)
            ->where('enabled', true)
            ->exists();
    }

    private function addonFeatureEnabled(Tenant $tenant, string $featureKey): bool
    {
        return $this->activeAddonFeatures($tenant, $featureKey)->isNotEmpty();
    }

    private function activeAddonFeatures(Tenant $tenant, string $featureKey)
    {
        return $tenant->tenantAddons()
            ->with('addon.features')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get()
            ->filter(fn ($tenantAddon) => $tenantAddon->addon?->is_active)
            ->pluck('addon.features')
            ->flatten()
            ->filter(fn ($feature) => $feature->feature_key === $featureKey && $feature->enabled)
            ->values();
    }

    private function tenantFlag(Tenant $tenant, string $featureKey): ?FeatureFlag
    {
        return $tenant->featureFlags()
            ->where('feature_key', $featureKey)
            ->first();
    }

    private function globalFlag(string $featureKey): ?FeatureFlag
    {
        return FeatureFlag::query()
            ->whereNull('tenant_id')
            ->where('feature_key', $featureKey)
            ->first();
    }

    private function enabledByGlobalFlag(string $featureKey): bool
    {
        return (bool) $this->globalFlag($featureKey)?->enabled;
    }
}

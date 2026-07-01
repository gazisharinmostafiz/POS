<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantResolver
{
    public function resolveTenant(Request $request): ?Tenant
    {
        if ($tenant = $this->resolveFromHeader($request)) {
            return $tenant;
        }

        if ($tenant = $this->resolveFromCustomDomain($request)) {
            return $tenant;
        }

        return $this->resolveFromSubdomain($request);
    }

    public function resolveBranch(Request $request, Tenant $tenant): ?Branch
    {
        $identifier = $request->header('X-Branch');

        if (! $identifier) {
            return null;
        }

        return $tenant->branches()
            ->where('is_active', true)
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }

    private function resolveFromHeader(Request $request): ?Tenant
    {
        $identifier = $request->header('X-Tenant');

        if (! $identifier) {
            return null;
        }

        return Tenant::query()
            ->where('is_active', true)
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier)
                    ->orWhere('domain', $identifier)
                    ->orWhere('subdomain', $identifier);

                if (ctype_digit($identifier)) {
                    $query->orWhere('id', (int) $identifier);
                }
            })
            ->first();
    }

    private function resolveFromCustomDomain(Request $request): ?Tenant
    {
        return Tenant::query()
            ->where('is_active', true)
            ->where('domain', $request->getHost())
            ->first();
    }

    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $rootDomain = config('tenancy.root_domain');
        $host = $request->getHost();

        if (! $rootDomain || $host === $rootDomain || ! Str::endsWith($host, '.'.$rootDomain)) {
            return null;
        }

        $subdomain = Str::before($host, '.'.$rootDomain);

        return Tenant::query()
            ->where('is_active', true)
            ->where('subdomain', $subdomain)
            ->first();
    }
}

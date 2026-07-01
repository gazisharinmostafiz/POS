<?php

namespace App\Services\Tenancy;

use App\Models\Branch;
use App\Models\Tenant;

class TenantContext
{
    private ?Tenant $tenant = null;

    private ?Branch $branch = null;

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        if ($tenant === null || ($this->branch && $this->branch->tenant_id !== $tenant->id)) {
            $this->branch = null;
        }
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setBranch(?Branch $branch): void
    {
        if ($branch && $this->tenant && (int) $branch->tenant_id !== (int) $this->tenant->id) {
            throw new \InvalidArgumentException('Branch does not belong to the current tenant.');
        }

        $this->branch = $branch;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->branch = null;
    }
}

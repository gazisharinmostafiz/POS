<?php

namespace App\Support;

use App\Models\User;

class BroadcastChannelAuthorization
{
    public static function tenant(User $user, int $tenantId): bool
    {
        return (int) $user->tenant_id === $tenantId;
    }

    public static function tenantBranch(User $user, int $tenantId, int $branchId): bool
    {
        return self::tenant($user, $tenantId)
            && ((int) $user->branch_id === $branchId || $user->branch_id === null);
    }
}

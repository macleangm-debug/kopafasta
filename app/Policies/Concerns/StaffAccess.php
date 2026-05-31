<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Services\RoleService;

trait StaffAccess
{
    protected function hasPolicyBypass(User $user): bool
    {
        return app(RoleService::class)->hasPolicyBypass($user);
    }

    protected function sameBranch(User $user, ?int $recordBranchId): bool
    {
        if ($this->hasPolicyBypass($user)) {
            return true;
        }

        if (! $user->branch_id || ! $recordBranchId) {
            return false;
        }

        return (int) $user->branch_id === (int) $recordBranchId;
    }
}

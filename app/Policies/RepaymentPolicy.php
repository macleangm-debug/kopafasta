<?php

namespace App\Policies;

use App\Models\Repayment;
use App\Models\User;

class RepaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector'], true);
    }

    public function view(User $user, Repayment $repayment): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $repayment->loan?->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector'], true);
    }

    private function sameBranch(User $user, ?int $recordBranchId): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if (! $user->branch_id || ! $recordBranchId) {
            return false;
        }

        return (int) $user->branch_id === (int) $recordBranchId;
    }
}

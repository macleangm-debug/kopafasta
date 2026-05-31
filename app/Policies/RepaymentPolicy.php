<?php

namespace App\Policies;

use App\Models\Repayment;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class RepaymentPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin', 'collector'], true);
    }

    public function view(User $user, Repayment $repayment): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $repayment->loan?->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin', 'collector'], true);
    }
}

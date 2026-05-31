<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class LoanPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin', 'collector'], true);
    }

    public function view(User $user, Loan $loan): bool
    {
        return $this->viewAny($user) && $this->sameBranch($user, $loan->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function update(User $user, Loan $loan): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $loan->customer?->branch_id);
    }

    public function delete(User $user, Loan $loan): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $loan->customer?->branch_id);
    }

    public function disburse(User $user, Loan $loan): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $loan->customer?->branch_id);
    }
}

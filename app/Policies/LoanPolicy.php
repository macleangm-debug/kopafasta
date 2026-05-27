<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector'], true);
    }

    public function view(User $user, Loan $loan): bool
    {
        return $this->viewAny($user) && $this->sameBranch($user, $loan->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true);
    }

    public function update(User $user, Loan $loan): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true)
            && $this->sameBranch($user, $loan->customer?->branch_id);
    }

    public function delete(User $user, Loan $loan): bool
    {
        return in_array($user->role, ['manager', 'admin'], true)
            && $this->sameBranch($user, $loan->customer?->branch_id);
    }

    public function disburse(User $user, Loan $loan): bool
    {
        return in_array($user->role, ['manager', 'admin'], true)
            && $this->sameBranch($user, $loan->customer?->branch_id);
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

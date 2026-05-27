<?php

namespace App\Policies;

use App\Models\Disbursement;
use App\Models\User;

class DisbursementPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true);
    }

    public function view(User $user, Disbursement $disbursement): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true);
    }

    public function update(User $user, Disbursement $disbursement): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }

    public function delete(User $user, Disbursement $disbursement): bool
    {
        return in_array($user->role, ['manager', 'admin'], true)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }

    public function release(User $user, Disbursement $disbursement): bool
    {
        return in_array($user->role, ['manager', 'admin'], true)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
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

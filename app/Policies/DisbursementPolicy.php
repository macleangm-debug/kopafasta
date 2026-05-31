<?php

namespace App\Policies;

use App\Models\Disbursement;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class DisbursementPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function view(User $user, Disbursement $disbursement): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function update(User $user, Disbursement $disbursement): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }

    public function delete(User $user, Disbursement $disbursement): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }

    public function release(User $user, Disbursement $disbursement): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $disbursement->loan?->customer?->branch_id);
    }
}

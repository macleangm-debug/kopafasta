<?php

namespace App\Policies;

use App\Models\LoanTopUpRequest;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class LoanTopUpRequestPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function view(User $user, LoanTopUpRequest $request): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $request->loan?->customer?->branch_id);
    }

    public function update(User $user, LoanTopUpRequest $request): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $request->loan?->customer?->branch_id);
    }

    public function approve(User $user, LoanTopUpRequest $request): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $request->loan?->customer?->branch_id);
    }
}

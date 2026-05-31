<?php

namespace App\Policies;

use App\Models\RestructureRequest;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class RestructureRequestPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function view(User $user, RestructureRequest $restructureRequest): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $restructureRequest->loan?->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function update(User $user, RestructureRequest $restructureRequest): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $restructureRequest->loan?->customer?->branch_id);
    }

    public function delete(User $user, RestructureRequest $restructureRequest): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $restructureRequest->loan?->customer?->branch_id);
    }

    public function approve(User $user, RestructureRequest $restructureRequest): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $restructureRequest->loan?->customer?->branch_id);
    }
}

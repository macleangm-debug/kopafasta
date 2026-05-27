<?php

namespace App\Policies;

use App\Models\ArrearCase;
use App\Models\User;

class ArrearCasePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['collector', 'officer', 'manager', 'admin'], true);
    }

    public function view(User $user, ArrearCase $arrearCase): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $arrearCase->loan?->customer?->branch_id);
    }

    public function update(User $user, ArrearCase $arrearCase): bool
    {
        return in_array($user->role, ['collector', 'officer', 'manager', 'admin'], true)
            && $this->sameBranch($user, $arrearCase->loan?->customer?->branch_id);
    }

    public function addAction(User $user, ArrearCase $arrearCase): bool
    {
        return in_array($user->role, ['collector', 'officer', 'manager', 'admin'], true)
            && $this->sameBranch($user, $arrearCase->loan?->customer?->branch_id);
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

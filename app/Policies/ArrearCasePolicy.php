<?php

namespace App\Policies;

use App\Models\ArrearCase;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class ArrearCasePolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['collector', 'officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function view(User $user, ArrearCase $arrearCase): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $arrearCase->loan?->customer?->branch_id);
    }

    public function update(User $user, ArrearCase $arrearCase): bool
    {
        return in_array($user->role, ['collector', 'officer', 'manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $arrearCase->loan?->customer?->branch_id);
    }

    public function addAction(User $user, ArrearCase $arrearCase): bool
    {
        return in_array($user->role, ['collector', 'officer', 'manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $arrearCase->loan?->customer?->branch_id);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector'], true);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        $codes = app(\App\Services\CreditDeskAssignmentService::class)->departmentCodes($user);

        return in_array('PRT', $codes, true);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return in_array($user->role, ['admin'], true);
    }
}

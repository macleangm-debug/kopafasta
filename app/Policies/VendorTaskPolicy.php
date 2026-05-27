<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VendorTask;

class VendorTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector'], true);
    }

    public function view(User $user, VendorTask $vendorTask): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true);
    }

    public function update(User $user, VendorTask $vendorTask): bool
    {
        return $this->create($user);
    }

    public function complete(User $user, VendorTask $vendorTask): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector'], true);
    }

    public function delete(User $user, VendorTask $vendorTask): bool
    {
        return in_array($user->role, ['manager', 'admin'], true);
    }
}

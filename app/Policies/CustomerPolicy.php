<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;

class CustomerPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin', 'credit_analyst', 'collector'], true);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user) && $this->sameBranch($user, $customer->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'super_admin'], true);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->create($user) && $this->sameBranch($user, $customer->branch_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true)
            && $this->sameBranch($user, $customer->branch_id);
    }
}

<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'credit_analyst', 'collector'], true);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->viewAny($user) && $this->sameBranch($user, $customer->branch_id);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin'], true);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->create($user) && $this->sameBranch($user, $customer->branch_id);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return in_array($user->role, ['manager', 'admin'], true)
            && $this->sameBranch($user, $customer->branch_id);
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

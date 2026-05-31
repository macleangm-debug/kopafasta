<?php

namespace App\Policies;

use App\Models\LoanProduct;
use App\Models\User;

class LoanProductPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['customer', 'borrower', 'officer', 'manager', 'admin', 'super_admin', 'credit_analyst', 'collector'], true);
    }

    public function view(User $user, LoanProduct $loanProduct): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['manager', 'admin', 'super_admin'], true);
    }

    public function update(User $user, LoanProduct $loanProduct): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, LoanProduct $loanProduct): bool
    {
        return $user->role === 'admin';
    }
}

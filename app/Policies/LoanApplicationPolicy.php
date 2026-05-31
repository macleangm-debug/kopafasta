<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;
use App\Policies\Concerns\StaffAccess;
use App\Services\PermissionService;

class LoanApplicationPolicy
{
    use StaffAccess;

    public function viewAny(User $user): bool
    {
        return app(PermissionService::class)->has($user, 'applications.view');
    }

    public function view(User $user, LoanApplication $loanApplication): bool
    {
        return $this->viewAny($user)
            && $this->sameBranch($user, $loanApplication->branch_id ?: $loanApplication->customer?->branch_id);
    }

    public function create(User $user): bool
    {
        return app(PermissionService::class)->has($user, 'applications.edit');
    }

    public function update(User $user, LoanApplication $loanApplication): bool
    {
        return app(PermissionService::class)->has($user, 'applications.edit')
            && $this->sameBranch($user, $loanApplication->branch_id ?: $loanApplication->customer?->branch_id);
    }

    public function delete(User $user, LoanApplication $loanApplication): bool
    {
        return app(PermissionService::class)->has($user, 'applications.reject')
            && $this->sameBranch($user, $loanApplication->branch_id ?: $loanApplication->customer?->branch_id);
    }

    public function transition(User $user, LoanApplication $loanApplication): bool
    {
        $permissions = app(PermissionService::class);

        return $this->view($user, $loanApplication)
            && $permissions->hasAny($user, [
                'applications.acknowledge',
                'applications.review',
                'applications.pre_approve',
                'applications.approve',
                'applications.reject',
                'applications.disburse',
            ]);
    }
}

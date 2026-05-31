<?php

namespace App\Policies;

use App\Models\LoanApplication;
use App\Models\User;
use App\Services\PermissionService;

class LoanApplicationPolicy
{
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

    private function sameBranch(User $user, ?int $recordBranchId): bool
    {
        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        if (! $user->branch_id || ! $recordBranchId) {
            return false;
        }

        return (int) $user->branch_id === (int) $recordBranchId;
    }
}

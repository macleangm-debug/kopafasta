<?php

namespace App\Policies;

use App\Models\PartnerTask;
use App\Models\User;

class VendorTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector', 'partner_support'], true)
            || app(\App\Services\PartnerStaffService::class)->managesPartners($user);
    }

    public function view(User $user, PartnerTask $vendorTask): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'partner_support'], true)
            || app(\App\Services\PartnerStaffService::class)->managesPartners($user);
    }

    public function update(User $user, PartnerTask $vendorTask): bool
    {
        return $this->create($user);
    }

    public function complete(User $user, PartnerTask $vendorTask): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector', 'partner_support'], true)
            || app(\App\Services\PartnerStaffService::class)->managesPartners($user);
    }

    public function delete(User $user, PartnerTask $vendorTask): bool
    {
        return in_array($user->role, ['manager', 'admin'], true);
    }

    public function reassign(User $user, PartnerTask $vendorTask): bool
    {
        return app(\App\Services\PartnerTaskReassignmentService::class)->can($user, $vendorTask);
    }
}

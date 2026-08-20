<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['officer', 'manager', 'admin', 'collector', 'partner_support'], true)
            || app(\App\Services\PartnerStaffService::class)->managesPartners($user);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return app(\App\Services\PartnerStaffService::class)->managesPartners($user);
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

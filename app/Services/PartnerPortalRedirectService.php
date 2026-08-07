<?php

namespace App\Services;

use App\Models\User;
use App\Models\Vendor;

class PartnerPortalRedirectService
{
    public function homeUrl(User $user): string
    {
        if ($user->role !== 'vendor') {
            return route('site.borrower.dashboard');
        }

        $vendor = Vendor::query()->where('user_id', $user->id)->first();

        if ($vendor?->isAffiliate()) {
            return route('site.affiliate.dashboard');
        }

        if ($vendor?->portalShell() === 'supplier') {
            return route('site.partner.supplier.dashboard');
        }

        if ($vendor?->portalShell() === 'capital') {
            return route('site.investor.dashboard');
        }

        return route('site.partner.dashboard');
    }

    public function isAffiliateUser(User $user): bool
    {
        if ($user->role !== 'vendor') {
            return false;
        }

        $vendor = Vendor::query()->where('user_id', $user->id)->first();

        return (bool) ($vendor?->isAffiliate());
    }
}

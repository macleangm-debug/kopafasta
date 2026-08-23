<?php

namespace App\Services;

use App\Models\Lender;
use App\Models\Partner;
use App\Support\PhoneNumber;

class PartnerProfileTabs
{
    /** @return array<string, string> */
    public function tabs(Partner $partner, bool $canSeePayouts = false): array
    {
        $tabs = ['profile' => 'Profile'];

        if ($this->showsJobs($partner)) {
            $tabs['jobs'] = 'Jobs';
        }
        if ($this->showsCases($partner)) {
            $tabs['cases'] = 'Cases';
        }
        if ($this->showsPipeline($partner)) {
            $tabs['pipeline'] = 'Pipeline';
        }
        if ($this->showsListings($partner)) {
            $tabs['listings'] = 'Listings';
        }
        if ($this->showsCapital($partner)) {
            $tabs['capital'] = 'Capital';
        }
        if ($this->showsPerformance($partner)) {
            $tabs['performance'] = 'Performance';
        }
        if ($canSeePayouts) {
            $tabs['payouts'] = 'Payouts';
        }

        $tabs['portal'] = 'Portal';
        $tabs['account'] = 'Account';

        return $tabs;
    }

    public function showsJobs(Partner $partner): bool
    {
        return $partner->isValuer() || $partner->isGpsInstaller() || $partner->isInsurance();
    }

    public function showsCases(Partner $partner): bool
    {
        return $partner->isRecoveryPartner() || $partner->hasPartnerRole('towing');
    }

    public function showsPipeline(Partner $partner): bool
    {
        return $partner->isAffiliate() || $partner->hasPartnerRole('affiliate');
    }

    public function showsListings(Partner $partner): bool
    {
        return $partner->isSupplier() || $partner->hasPartnerRole('supplier');
    }

    public function showsCapital(Partner $partner): bool
    {
        return $partner->isCapitalPartner();
    }

    public function showsFieldPerformance(Partner $partner): bool
    {
        return $this->showsJobs($partner) || $this->showsCases($partner);
    }

    public function showsPerformance(Partner $partner): bool
    {
        return $this->showsFieldPerformance($partner)
            || $this->showsPipeline($partner)
            || $this->showsListings($partner);
    }

    public function linkedLender(Partner $partner): ?Lender
    {
        if (filled($partner->user_id)) {
            $byUser = Lender::query()->where('user_id', $partner->user_id)->first();
            if ($byUser) {
                return $byUser;
            }
        }

        $phone = PhoneNumber::digits((string) $partner->phone);
        if ($phone !== '') {
            $byPhone = Lender::query()
                ->where('phone', $phone)
                ->orWhere('phone', $partner->phone)
                ->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        if (filled($partner->email)) {
            return Lender::query()->where('email', $partner->email)->first();
        }

        return null;
    }
}

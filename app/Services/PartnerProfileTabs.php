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
        $tabs = ['profile' => 'Overview'];

        if ($this->showsJobs($partner)) {
            $tabs['jobs'] = 'Jobs';
        }
        if ($this->showsCases($partner)) {
            $tabs['cases'] = 'Cases';
        }
        if ($this->showsPipeline($partner)) {
            $tabs['pipeline'] = 'Business';
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
            $tabs['payouts'] = 'Earnings';
        }
        if ($this->showsPipeline($partner)
            || ($this->showsFieldGovernance($partner) && (
                app(PartnerMembershipService::class)->requiresPayment($partner)
                || filled($partner->membership_started_at)
            ))) {
            $tabs['membership'] = 'Membership';
        }
        if ($this->showsFieldGovernance($partner)) {
            $tabs['compliance'] = 'Compliance';
            $tabs['documents'] = 'Documents';
        }
        if ($this->showsPipeline($partner) || $this->showsFieldGovernance($partner)) {
            $tabs['agreements'] = 'Agreements';
        }
        if ($this->showsFieldGovernance($partner)) {
            $tabs['history'] = 'History';
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

    public function showsFieldGovernance(Partner $partner): bool
    {
        return app(PartnerEfficiencyPolicy::class)->isGoverned($partner);
    }

    public function showsFieldPerformance(Partner $partner): bool
    {
        if ($partner->isTowing() || $partner->isYard()) {
            return $partner->tasks()->exists() || $partner->recoveryAssignments()->exists();
        }

        return $this->showsJobs($partner) || ($this->showsCases($partner) && ! $partner->isTowing());
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

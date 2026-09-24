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
        $tabs = ['profile' => __('admin.partners.tab_overview')];

        if ($this->showsJobs($partner)) {
            $tabs['jobs'] = __('admin.partners.tab_jobs');
        }
        if ($this->showsCases($partner)) {
            $tabs['cases'] = __('admin.partners.tab_cases');
        }
        if ($this->showsPipeline($partner)) {
            $tabs['pipeline'] = __('admin.partners.tab_business');
        }
        if ($this->showsListings($partner)) {
            $tabs['listings'] = __('admin.partners.tab_listings');
        }
        if ($this->showsCapital($partner)) {
            $tabs['capital'] = __('admin.partners.tab_capital');
        }
        if ($this->showsPerformance($partner)) {
            $tabs['performance'] = __('admin.partners.tab_performance');
        }
        if ($canSeePayouts) {
            $tabs['payouts'] = __('admin.partners.tab_earnings');
        }
        if ($this->showsPipeline($partner)
            || ($this->showsFieldGovernance($partner) && (
                app(PartnerMembershipService::class)->requiresPayment($partner)
                || filled($partner->membership_started_at)
            ))) {
            $tabs['membership'] = __('admin.partners.tab_membership');
        }
        if ($this->showsFieldGovernance($partner)) {
            $tabs['compliance'] = __('admin.partners.tab_compliance');
            $tabs['documents'] = __('admin.partners.tab_documents');
        }
        if ($this->showsPipeline($partner) || $this->showsFieldGovernance($partner)) {
            $tabs['agreements'] = __('admin.partners.tab_agreements');
        }
        if ($this->showsFieldGovernance($partner)) {
            $tabs['history'] = __('admin.partners.tab_history');
        }

        $tabs['portal'] = __('admin.partners.tab_portal');
        $tabs['account'] = __('admin.partners.tab_account');

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

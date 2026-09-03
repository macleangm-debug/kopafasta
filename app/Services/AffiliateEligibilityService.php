<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Vendor;
use App\Support\AffiliatePerformanceStatus;

class AffiliateEligibilityService
{
    /**
     * Single commercial-eligibility answer for promo codes, referrals, dashboard, and new commission.
     *
     * @return array{
     *     can_operate: bool,
     *     can_attribute: bool,
     *     can_share: bool,
     *     can_earn_new: bool,
     *     can_access_portal: bool,
     *     reasons: list<string>,
     *     application: string,
     *     account: string,
     *     kyc: string,
     *     membership: string,
     *     performance: string,
     *     compliance: string
     * }
     */
    public function for(Vendor|Partner $affiliate): array
    {
        $reasons = [];
        $membership = app(AffiliateMembershipService::class);
        $lifecycle = app(AffiliateLifecycleService::class);
        $membershipCfg = AffiliateMembershipService::config();

        $accountActive = ($affiliate->status ?? '') === 'active';
        $kycOk = in_array($affiliate->affiliate_kyc_status, ['verified', 'approved'], true)
            || ! app(AffiliateSettingsService::class)->requireKycForVerification();
        $premiumAgreement = $membership->usesPremiumAgreement($affiliate);
        $membershipRequired = $premiumAgreement
            ? false
            : ($membershipCfg['enabled'] && $membershipCfg['required_before_sharing']);
        $membershipActive = $premiumAgreement
            ? $membership->isActive($affiliate)
            : (! $membershipRequired || $membership->isActive($affiliate));
        $promoAfterExpiry = ($membershipCfg['promo_code_on_expiry'] ?? 'disable') === 'keep';
        $commissionAfterExpiry = ($membershipCfg['commission_after_expiry'] ?? 'historical_only') === 'continue';
        $membershipOk = $premiumAgreement
            ? $membershipActive
            : ($membershipActive || $promoAfterExpiry);
        $termsOk = ! ($membershipCfg['require_terms_before_activation'] ?? true)
            || app(AffiliateTermsService::class)->hasAccepted($affiliate)
            || $this->legacyMembershipAlreadyActive($affiliate, $membershipCfg);

        $performance = (string) ($affiliate->affiliate_performance_status ?: AffiliatePerformanceStatus::RAMP_UP);
        $performanceOk = $affiliate->isPremiumAffiliate()
            || ! AffiliatePerformanceStatus::blocksNewBusiness($performance);

        $complianceStatus = $lifecycle->statusFor($affiliate);
        $complianceOk = ! in_array($complianceStatus, [
            AffiliateLifecycleService::SUSPENDED,
            AffiliateLifecycleService::TERMINATED,
        ], true);

        $fraudOk = ! app(AffiliateFraudDetectionService::class)->referralsBlocked($affiliate);

        if (! $accountActive) {
            $reasons[] = 'account_inactive';
        }
        if (! $kycOk) {
            $reasons[] = 'kyc_unverified';
        }
        if (! $membershipOk) {
            $reasons[] = $premiumAgreement ? 'agreement_inactive' : 'membership_inactive';
        }
        if (! $termsOk) {
            $reasons[] = 'terms_unaccepted';
        }
        if (! $performanceOk) {
            $reasons[] = 'performance_suspended';
        }
        if (! $complianceOk) {
            $reasons[] = $complianceStatus === AffiliateLifecycleService::TERMINATED
                ? 'compliance_terminated'
                : 'compliance_suspended';
        }
        if (! $fraudOk) {
            $reasons[] = 'fraud_blocked';
        }

        $canAttribute = $accountActive && $kycOk && $membershipOk && $termsOk
            && $performanceOk && $complianceOk && $fraudOk;
        $canEarn = $accountActive && $kycOk && ($membershipActive || $commissionAfterExpiry) && $termsOk
            && $performanceOk && $complianceOk && $fraudOk;

        return [
            'can_operate' => $canAttribute,
            'can_attribute' => $canAttribute,
            'can_share' => $canAttribute,
            'can_earn_new' => $canEarn,
            'can_access_portal' => $lifecycle->canAccessPortal($affiliate),
            'reasons' => $reasons,
            'application' => 'approved',
            'account' => $accountActive ? 'active' : (string) ($affiliate->status ?: 'inactive'),
            'kyc' => $kycOk ? 'verified' : (string) ($affiliate->affiliate_kyc_status ?: 'pending'),
            'membership' => $membership->summary($affiliate)['status'],
            'performance' => $performance,
            'compliance' => $complianceStatus,
        ];
    }

    public function canAttributeNewReferral(Vendor|Partner $affiliate): bool
    {
        return $this->for($affiliate)['can_attribute'];
    }

    public function canSharePromo(Vendor|Partner $affiliate): bool
    {
        return $this->for($affiliate)['can_share'];
    }

    public function canEarnFromNewBusiness(Vendor|Partner $affiliate): bool
    {
        return $this->for($affiliate)['can_earn_new'];
    }

    /**
     * Affiliates who already paid membership before Terms existed stay commercially eligible
     * until they accept on next renewal.
     *
     * @param  array<string, mixed>  $membershipCfg
     */
    private function legacyMembershipAlreadyActive(Vendor|Partner $affiliate, array $membershipCfg): bool
    {
        if (! ($membershipCfg['enabled'] ?? true)) {
            return true;
        }

        return app(AffiliateMembershipService::class)->isActive($affiliate)
            && filled($affiliate->membership_started_at);
    }
}

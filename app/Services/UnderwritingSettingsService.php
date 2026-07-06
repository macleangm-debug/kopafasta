<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;

class UnderwritingSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::get("underwriting.$key", $default);
    }

    public function guarantorInvitationExpiryDays(): int
    {
        return max(1, (int) $this->get('guarantor_invitation_expiry_days', 14));
    }

    public function documentRequestDefaultDueDays(): int
    {
        return max(1, (int) $this->get('document_request_default_due_days', 7));
    }

    public function blockAcknowledgeWithoutGuarantor(): bool
    {
        return (bool) $this->get('block_acknowledge_without_guarantor', true);
    }

    public function holdApplicationsUntilGuarantorApproved(): bool
    {
        return (bool) $this->get('hold_applications_until_guarantor_approved', true);
    }

    public function defaultRateTierCount(): int
    {
        return max(2, min(8, (int) $this->get(
            'default_rate_tier_count',
            config('loan_product_rate_tiers.tier_count', 4),
        )));
    }

    public function defaultRateDiscountFraction(): float
    {
        $value = (float) $this->get(
            'default_rate_discount_fraction',
            config('loan_product_rate_tiers.rate_discount_fraction', 0.30),
        );

        return max(0, min(0.85, $value));
    }

    public function stageSlaDays(): int
    {
        return max(1, (int) $this->get('stage_sla_days', 5));
    }

    public function loanReviewSlaLabel(?Customer $customer = null): string
    {
        $days = $this->stageSlaDays();

        if ($customer) {
            $priority = (int) (app(MemberEngagementRewardService::class)->underwritingBoosts($customer)['processing_priority'] ?? 0);
            if ($priority >= 3) {
                return __('borrower.loan_profile.sla_hours', ['hours' => 12]);
            }
            if ($priority >= 1) {
                return __('borrower.loan_profile.sla_hours', ['hours' => 24]);
            }
        }

        if ($days === 1) {
            return __('borrower.loan_profile.sla_hours', ['hours' => 24]);
        }

        return __('borrower.loan_profile.sla_days', ['days' => $days]);
    }

    public function counterOffersEnabled(): bool
    {
        return (bool) $this->get('enable_counter_offers', false);
    }

    public function assetBackedAlternativeEnabled(): bool
    {
        return (bool) $this->get('enable_asset_backed_alternative', false);
    }

    public function automaticRejectionEnabled(): bool
    {
        return (bool) $this->get('enable_automatic_rejection', true);
    }
}

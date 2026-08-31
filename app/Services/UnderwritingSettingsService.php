<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
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

    /** Days a submitted application may wait for guarantor completion before it is closed. */
    public function awaitingGuarantorDeadlineDays(): int
    {
        return max(1, (int) $this->get('awaiting_guarantor_deadline_days', 7));
    }

    /** Screening document/evidence requests cannot exceed this window. */
    public const SCREENING_REQUEST_MAX_DAYS = 7;

    public function documentRequestDefaultDueDays(): int
    {
        return max(1, min(self::SCREENING_REQUEST_MAX_DAYS, (int) $this->get('document_request_default_due_days', 7)));
    }

    /**
     * Days after the request was sent to fire reminders. Always before the due day.
     *
     * @return list<int>
     */
    public function documentRequestReminderOffsets(): array
    {
        $raw = $this->get('document_request_reminder_offsets', '3,5,6');
        $due = $this->documentRequestDefaultDueDays();
        $offsets = collect(is_array($raw) ? $raw : preg_split('/\s*,\s*/', (string) $raw))
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day > 0 && $day < $due)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $offsets !== [] ? $offsets : [3, 5, 6];
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

    /** Park capacity-fail applications and auto-reject after delay. */
    public function capacityAutoRejectEnabled(): bool
    {
        return $this->automaticRejectionEnabled()
            && (bool) $this->get('enable_capacity_auto_reject', true);
    }

    public function capacityAutoRejectDelayHours(): int
    {
        return max(1, min(168, (int) $this->get('capacity_auto_reject_delay_hours', 12)));
    }

    public function verifiedCapacityAutoRejectDelayHours(): int
    {
        return max(1, min(168, (int) $this->get('verified_capacity_auto_reject_delay_hours', 6)));
    }

    public function groupMemberHardFailAction(): string
    {
        $value = (string) $this->get('group_member_hard_fail_action', CreditEligibilityPolicyService::GROUP_FAIL_REPLACE);

        return in_array($value, [
            CreditEligibilityPolicyService::GROUP_FAIL_REPLACE,
            CreditEligibilityPolicyService::GROUP_FAIL_REJECT,
        ], true) ? $value : CreditEligibilityPolicyService::GROUP_FAIL_REPLACE;
    }

    public function guarantorHardFailAction(): string
    {
        $value = (string) $this->get('guarantor_hard_fail_action', CreditEligibilityPolicyService::GUARANTOR_FAIL_REPLACE);

        return in_array($value, [
            CreditEligibilityPolicyService::GUARANTOR_FAIL_REPLACE,
            CreditEligibilityPolicyService::GUARANTOR_FAIL_REJECT,
        ], true) ? $value : CreditEligibilityPolicyService::GUARANTOR_FAIL_REPLACE;
    }

    public function guarantorReplacementHours(): int
    {
        return max(1, min(168, (int) $this->get('guarantor_replacement_hours', 48)));
    }

    public function guarantorGateRequired(int $gate, ?LoanApplication $application = null): bool
    {
        $key = $gate === 2 ? 'guarantor_gate_2_required' : 'guarantor_gate_1_required';
        if ($application) {
            $product = $application->product;
            if ($product && ! $this->guarantorRequiredForProduct($application)) {
                return false;
            }
            if ($product && $product->getAttribute($key) !== null) {
                return (bool) $product->getAttribute($key);
            }
        }

        return (bool) $this->get($key, false);
    }

    public function guarantorRequiredForProduct(LoanApplication $application): bool
    {
        $product = $application->product;
        if ($product && isset($product->requires_guarantor)) {
            return (bool) $product->requires_guarantor;
        }

        return (bool) $this->get('guarantor_required', false);
    }

    public function minimumAcceptableGuarantors(LoanApplication $application): int
    {
        $product = $application->product;
        $fromProduct = (int) ($product->min_guarantors ?? $product->guarantors_required ?? 0);
        if ($fromProduct > 0) {
            return $fromProduct;
        }

        return max(0, (int) $this->get('minimum_acceptable_guarantors', $this->guarantorRequiredForProduct($application) ? 1 : 0));
    }

    /** Frozen copy of the screening-gate settings at decision time. */
    public function policySnapshot(): array
    {
        return [
            'capacity_auto_reject_delay_hours' => $this->capacityAutoRejectDelayHours(),
            'verified_capacity_auto_reject_delay_hours' => $this->verifiedCapacityAutoRejectDelayHours(),
            'group_member_hard_fail_action' => $this->groupMemberHardFailAction(),
            'guarantor_hard_fail_action' => $this->guarantorHardFailAction(),
            'guarantor_replacement_hours' => $this->guarantorReplacementHours(),
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public function collateralSecureDecisionDays(): int
    {
        return max(1, (int) $this->get('collateral_secure_decision_days', 3));
    }

    public function insuranceExpiryBufferMonths(): int
    {
        return max(0, (int) $this->get('insurance_expiry_buffer_months', 2));
    }

    public function insuranceRenewalDecisionDays(): int
    {
        return max(1, (int) $this->get('insurance_renewal_decision_days', 5));
    }

    public function collateralSecureGraceDays(): int
    {
        return max(0, (int) $this->get('collateral_secure_grace_days', 3));
    }

    public function collateralInsuranceRatePercent(): float
    {
        return app(PartnerDefaultsService::class)->insuranceRatePercent();
    }

    public function collateralInsuranceMarkupPercent(): float
    {
        return app(PartnerDefaultsService::class)->insuranceMarkupPercent();
    }

    /** Working days after contract acceptance before standard disbursement is due. */
    public function disbursementSlaWorkingDays(): int
    {
        return max(1, min(10, (int) $this->get('disbursement_sla_working_days', 2)));
    }

    /** Optional paid fast-track after offer acceptance (off by default). */
    public function disbursementFastTrackEnabled(): bool
    {
        return (bool) $this->get('enable_disbursement_fast_track', false);
    }

    public function disbursementFastTrackBusinessHours(): int
    {
        return max(1, min(72, (int) $this->get('disbursement_fast_track_business_hours', 12)));
    }

    /** Fixed rush fee in local currency (TZS). */
    public function disbursementFastTrackFeeAmount(): float
    {
        return max(0, round((float) $this->get('disbursement_fast_track_fee_amount', 25000), 2));
    }

    /**
     * Saved post-approval condition overlays. Catalog defaults live on
     * PostApprovalNextActionService — this is the Settings Hub overlay only.
     *
     * @return list<array<string, mixed>>
     */
    public function postApprovalConditionRows(): array
    {
        $raw = $this->get('post_approval_conditions', []);
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter($raw, fn ($row) => is_array($row) && filled($row['key'] ?? null)));
    }
}

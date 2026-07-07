<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Repayment;
use App\Models\RepaymentSchedule;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class MemberEngagementRewardService
{
    public function __construct(
        private readonly MemberEngagementService $engagement,
        private readonly LoyaltyPointsService $loyalty,
        private readonly GamificationSettingsService $settings,
    ) {}

    /** @return array{limit_multiplier: float, rate_discount_fraction: float, processing_priority: int, factors: list<array{label: string, detail: string}>} */
    public function underwritingBoosts(Customer $customer): array
    {
        $config = $this->boostConfig();
        $level = $this->engagement->referralLevel($customer);
        $trust = $this->engagement->trustScore($customer);

        $levelKey = (string) ($level['key'] ?? 'bronze');
        $levelBoost = $config['referral_level'][$levelKey] ?? $config['referral_level']['bronze'] ?? [];

        $trustPercent = (int) ($trust['percent'] ?? 0);
        $trustStep = max(0, min(5, (int) floor($trustPercent / 20)));

        $limitMultiplier = (float) ($levelBoost['limit_multiplier'] ?? 1.0)
            + ($trustStep * (float) ($config['trust_score']['limit_multiplier_per_step'] ?? 0.02));

        $rateDiscount = (float) ($levelBoost['rate_discount'] ?? 0.0)
            + ($trustStep * (float) ($config['trust_score']['rate_discount_per_step'] ?? 0.002));

        $processingPriority = (int) ($levelBoost['processing_priority'] ?? 0)
            + (int) round($trustStep * (float) ($config['trust_score']['processing_priority_per_step'] ?? 0.5));

        $factors = [];
        if (($levelBoost['limit_multiplier'] ?? 1.0) > 1.0) {
            $factors[] = [
                'label'  => __('borrower.engagement.boost.referral_level'),
                'detail' => $level['label'] ?? $levelKey,
            ];
        }
        if ($trustPercent >= 40) {
            $factors[] = [
                'label'  => __('borrower.engagement.boost.trust_score'),
                'detail' => $trustPercent.'%',
            ];
        }

        return [
            'limit_multiplier'        => round(max(1.0, $limitMultiplier), 4),
            'rate_discount_fraction'  => round(max(0.0, min(0.05, $rateDiscount)), 4),
            'processing_priority'     => max(0, min(10, $processingPriority)),
            'factors'                 => $factors,
        ];
    }

    public function afterProfileSectionSaved(Customer $customer, string $section): void
    {
        $this->loyalty->earn(
            $customer->fresh(),
            'update_information',
            'Profile section updated: '.$section,
            'profile_section',
            crc32($section),
        );

        $this->maybeAwardProfileComplete($customer->fresh());
    }

    public function afterDocumentUploaded(Customer $customer, string $documentCode): void
    {
        $this->loyalty->earn(
            $customer->fresh(),
            'upload_documents',
            'Document uploaded: '.$documentCode,
            'customer_document',
            crc32($documentCode),
        );

        $this->maybeAwardProfileComplete($customer->fresh());
    }

    public function afterRepaymentSchedulePaid(RepaymentSchedule $schedule, Repayment $repayment): void
    {
        if ($schedule->status !== 'paid' || ! $schedule->paid_at || ! $schedule->due_date) {
            return;
        }

        $paidAt = Carbon::parse($schedule->paid_at);
        $dueDate = Carbon::parse($schedule->due_date)->endOfDay();

        if ($paidAt->gt($dueDate)) {
            return;
        }

        $schedule->loadMissing('loan.customer');
        $customer = $schedule->loan?->customer;
        if (! $customer) {
            return;
        }

        $this->loyalty->earn(
            $customer,
            'repay_on_time',
            'On-time repayment',
            RepaymentSchedule::class,
            (int) $schedule->id,
        );

        try {
            app(RepaymentStreakRewardService::class)->afterOnTimeRepayment($customer->fresh());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function maybeAwardProfileComplete(Customer $customer): void
    {
        $percent = (int) (app(ProfileCompletionService::class)->calculate($customer)['percent'] ?? 0);
        if ($percent < 100) {
            return;
        }

        $this->loyalty->earn(
            $customer,
            'complete_profile',
            'Profile 100% complete',
            Customer::class,
            (int) $customer->id,
        );
    }

    /** @return array<string, mixed> */
    private function boostConfig(): array
    {
        $stored = Setting::get('gamification.underwriting_boosts');

        return is_array($stored) && $stored !== []
            ? array_replace_recursive(config('gamification.underwriting_boosts', []), $stored)
            : config('gamification.underwriting_boosts', []);
    }
}

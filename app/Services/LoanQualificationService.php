<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Setting;

class LoanQualificationService
{
    public function config(): array
    {
        $loan = Setting::group('loan');

        return [
            'income_multiplier'          => (float) ($loan['qualification_income_multiplier'] ?? 4),
            'max_cap'                    => (int) ($loan['qualification_max_cap'] ?? 5_000_000),
            'good_history_multiplier'    => (float) ($loan['qualification_good_history_multiplier'] ?? 1.5),
            'good_history_cap'           => (int) ($loan['qualification_good_history_cap'] ?? 7_500_000),
            'membership_inactive_factor' => (float) ($loan['qualification_membership_inactive_factor'] ?? 0),
            'kyc_incomplete_factor'      => (float) ($loan['qualification_kyc_incomplete_factor'] ?? 0.5),
            'new_member_floor'           => (int) ($loan['qualification_new_member_floor'] ?? 0),
        ];
    }

    public function calculate(Customer $customer): array
    {
        $cfg = $this->config();
        $factors = [];

        $income = (float) ($customer->monthly_income ?? 0);
        if ($income <= 0 && $customer->income_range) {
            $income = (float) (config('income_ranges.'.$customer->income_range.'.midpoint') ?? 0);
            $factors[] = ['label' => 'Income range', 'detail' => income_range_label($customer->income_range) ?? $customer->income_range];
        } elseif ($income > 0) {
            $factors[] = ['label' => 'Declared income', 'detail' => format_money($income).'/month'];
        }

        $hasIncomeData = $income > 0 || (bool) $customer->income_range;
        $base = (int) min(max($income * $cfg['income_multiplier'], 0), $cfg['max_cap']);

        if (! $hasIncomeData && ($cfg['new_member_floor'] ?? 0) > 0) {
            $base = (int) min($cfg['new_member_floor'], $cfg['max_cap']);
            $factors[] = ['label' => 'New member floor', 'detail' => format_money($base).' (settings)'];
            $hasIncomeData = true;
        }

        $hasGoodHistory = Loan::where('customer_id', $customer->id)->where('status', 'closed')->exists();
        if ($hasGoodHistory) {
            $base = (int) min($base * $cfg['good_history_multiplier'], $cfg['good_history_cap']);
            $factors[] = ['label' => 'Repayment history', 'detail' => 'Bonus for fully repaid loan(s)'];
        }

        if ($customer->activity_type) {
            $factors[] = ['label' => 'Activity type', 'detail' => activity_type_label($customer->activity_type) ?? $customer->activity_type];
        }

        $kycPayload = $customer->kyc?->payload ?? [];
        $crbScore = $kycPayload['nida_verification']['search_score'] ?? null;
        if ($crbScore) {
            $factors[] = ['label' => 'CRB match score', 'detail' => (string) $crbScore];
        }

        // Compulsory membership is retired and must not change credit limits.

        $boosts = app(MemberEngagementRewardService::class)->underwritingBoosts($customer);
        if (($boosts['limit_multiplier'] ?? 1.0) > 1.0) {
            $base = (int) round($base * (float) $boosts['limit_multiplier']);
            foreach ($boosts['factors'] as $factor) {
                $factors[] = $factor;
            }
        }

        $profile = app(ProfileCompletionService::class)->calculate($customer);
        if ($profile['percent'] < 100) {
            $base = (int) ($base * $cfg['kyc_incomplete_factor']);
            $factors[] = ['label' => 'Profile completion', 'detail' => $profile['percent'].'% complete'];
        } else {
            $factors[] = ['label' => 'Profile completion', 'detail' => 'Complete'];
        }

        if ($customer->face_verification_status === 'verified') {
            $factors[] = ['label' => 'Face verification', 'detail' => 'Approved'];
        }

        if ($customer->no_physical_nida_card) {
            $factors[] = [
                'label'  => __('borrower.nida.no_card_factor_label'),
                'detail' => __('borrower.nida.no_card_factor_detail'),
            ];
        }

        return [
            'amount'    => max(0, $base),
            'has_data'  => $hasIncomeData,
            'factors'   => $factors,
            'summary'   => __('borrower.dashboard.eligibility_summary'),
            'boosts'    => $boosts,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;

class BorrowerFinancialHealthService
{
    public function __construct(
        private readonly MemberEngagementService $engagement,
        private readonly ProfileCompletionService $profileCompletion,
        private readonly LoanQualificationService $qualification,
        private readonly ReferralService $referrals,
        private readonly BorrowerCreditLimitService $creditLimit,
    ) {}

    /** @return array<string, mixed> */
    public function forCustomer(Customer $customer, ?Loan $activeLoan = null): array
    {
        $profile = $this->profileCompletion->calculate($customer);
        $percent = (int) ($profile['percent'] ?? 0);
        $trust = $this->engagement->trustScore($customer);
        $referral = $this->engagement->referralProgress($customer);
        $qualification = $this->qualification->calculate($customer);
        $strength = $this->engagement->profileStrength($percent);
        $streak = $this->engagement->repaymentStreak($customer);

        $nextAction = $this->nextRecommendedAction($customer, $percent, $activeLoan);

        return [
            'profile_completion' => [
                'percent'  => $percent,
                'strength' => $strength,
            ],
            'trust_score' => $trust,
            'repayment_score' => [
                'streak' => $streak['count'],
                'label'  => $streak['count'] > 0
                    ? __('borrower.engagement.streak.label', ['count' => $streak['count']])
                    : __('borrower.engagement.streak.none'),
            ],
            'active_loan' => [
                'has_loan' => (bool) $activeLoan,
                'label'    => $activeLoan
                    ? ($activeLoan->product?->localizedName() ?? $activeLoan->loan_number)
                    : __('borrower.dashboard.snapshot.no_active_loan'),
                'url'      => $activeLoan ? route('site.borrower.loans.show', $activeLoan) : null,
            ],
            'membership' => [
                'active' => $customer->isMembershipActive(),
                'label'  => $customer->isMembershipActive()
                    ? __('borrower.profile.member_active')
                    : __('borrower.profile.member_inactive'),
            ],
            'referral_progress' => $referral,
            'available_limit' => [
                'value' => $this->creditLimit->availableFormatted($customer),
            ],
            'loyalty_points' => (int) ($customer->loyalty_points ?? 0),
            'next_action' => $nextAction,
        ];
    }

    /** @return array{label: string, url: string|null, priority: string} */
    private function nextRecommendedAction(Customer $customer, int $profilePercent, ?Loan $activeLoan): array
    {
        if (! $customer->isMembershipActive()) {
            return [
                'label'    => __('borrower.engagement.next_action.membership'),
                'url'      => route('site.membership.renew'),
                'priority' => 'high',
            ];
        }

        if ($profilePercent < 100) {
            $sections = app(ProfileSectionBuilderService::class)->hubCards($customer);
            $incomplete = collect($sections)->first(fn (array $s) => ($s['status'] ?? '') !== 'complete');

            return [
                'label'    => $incomplete
                    ? __('borrower.engagement.next_action.profile_section', ['section' => $incomplete['label']])
                    : __('borrower.engagement.next_action.complete_profile'),
                'url'      => $incomplete['url'] ?? route('site.borrower.profile'),
                'priority' => 'medium',
            ];
        }

        if ($activeLoan) {
            return [
                'label'    => __('borrower.engagement.next_action.repay_on_time'),
                'url'      => route('site.borrower.loans.show', $activeLoan),
                'priority' => 'medium',
            ];
        }

        return [
            'label'    => __('borrower.engagement.next_action.refer'),
            'url'      => route('site.borrower.referrals'),
            'priority' => 'low',
        ];
    }
}

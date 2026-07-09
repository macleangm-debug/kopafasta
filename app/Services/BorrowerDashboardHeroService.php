<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\LoanApplication;

class BorrowerDashboardHeroService
{
    public function __construct(
        private readonly ApplicationRequirementsService $requirements,
        private readonly LoanBalanceService $balances,
        private readonly ActiveLoanServicingService $servicing,
    ) {}

    /** @return array<string, mixed> */
    public function forCustomer(Customer $customer, ?Loan $activeLoan = null, $nextDue = null): array
    {
        $activeLoan ??= Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring'])
            ->latest('disbursement_date')
            ->first();

        if ($activeLoan) {
            $servicing = $this->servicing->forLoan($activeLoan);
            $balance = $this->balances->breakdown($activeLoan);

            if ($activeLoan->status === 'arrears' || ($servicing['in_arrears'] ?? false)) {
                $daysLate = (int) ($servicing['days_past_due'] ?? 0);

                return [
                    'variant'   => 'arrears',
                    'title'     => __('borrower.dashboard.hero.payment_overdue'),
                    'subtitle'  => __('borrower.dashboard.hero.days_late', ['days' => max(1, $daysLate)]),
                    'amount'    => format_money((float) ($servicing['next_due_amount'] ?? $servicing['amount_in_arrears'] ?? 0)),
                    'meta'      => __('borrower.dashboard.hero.outstanding', ['amount' => format_money($balance['total_outstanding'])]),
                    'cta_label' => __('borrower.loans_page.make_payment'),
                    'cta_url'   => route('site.borrower.payments.create', ['loan' => $activeLoan->id]),
                ];
            }

            $daysRemaining = (int) ($servicing['days_remaining'] ?? 0);

            return [
                'variant'   => 'active_loan',
                'title'     => __('borrower.dashboard.hero.active_loan'),
                'subtitle'  => $servicing['next_due_date']
                    ? __('borrower.dashboard.hero.due_in_days', ['days' => max(0, $daysRemaining)])
                    : __('borrower.dashboard.hero.no_upcoming_due'),
                'amount'    => format_money((float) ($servicing['next_due_amount'] ?? 0)),
                'meta'      => __('borrower.dashboard.hero.outstanding', ['amount' => format_money($balance['total_outstanding'])]),
                'cta_label' => __('borrower.dashboard.hero.view_loan'),
                'cta_url'   => route('site.borrower.loans.show', $activeLoan),
            ];
        }

        $settledLoan = Loan::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'closed')
            ->where('outstanding_balance', '<=', 0)
            ->latest('updated_at')
            ->first();

        if ($settledLoan) {
            return [
                'variant'   => 'settled',
                'title'     => __('borrower.dashboard.hero.settled_title'),
                'subtitle'  => __('borrower.dashboard.hero.settled_subtitle'),
                'amount'    => null,
                'meta'      => $settledLoan->loan_number,
                'cta_label' => __('borrower.dashboard.hero.settled_cta'),
                'cta_url'   => route('site.borrower.loan-products'),
            ];
        }

        $underReview = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['rejected', 'disbursed', 'withdrawn'])
            ->whereIn('current_stage', ['screening', 'credit_review', 'approval', 'under_review'])
            ->latest()
            ->first();

        if ($underReview) {
            return [
                'variant'   => 'under_review',
                'title'     => __('borrower.dashboard.hero.under_review_title'),
                'subtitle'  => __('borrower.dashboard.hero.under_review_subtitle'),
                'amount'    => null,
                'meta'      => $underReview->application_number,
                'cta_label' => __('borrower.dashboard.hero.view_application'),
                'cta_url'   => route('site.borrower.application', $underReview),
            ];
        }

        $activeApplications = app(BorrowerApplicationsDashboardService::class)->applicationsForCustomer($customer);

        if ($activeApplications !== []) {
            $primary = $activeApplications[0];
            $count = count($activeApplications);
            $isDraft = (bool) ($primary['is_draft'] ?? false);

            return [
                'variant'            => 'applications',
                'title'              => $isDraft
                    ? __('borrower.dashboard.hero.active_draft_title')
                    : __('borrower.dashboard.hero.active_applications_title'),
                'subtitle'           => $isDraft
                    ? ($primary['status_detail'] ?? __('borrower.dashboard.hero.active_draft_subtitle'))
                    : __('borrower.dashboard.hero.active_applications_subtitle', ['count' => $count]),
                'amount'             => null,
                'meta'               => $primary['application_number'] ?? null,
                'cta_label'          => $count > 1
                    ? __('borrower.dashboard.hero.view_applications')
                    : __('borrower.dashboard.hero.view_application'),
                'cta_url'            => route('site.borrower.loans', ['tab' => 'applications']),
                'secondary_cta_label'=> __('borrower.dashboard.hero.apply_now'),
                'secondary_cta_url'  => route('site.borrower.loan-products'),
            ];
        }

        $requirements = $this->requirements->checklist($customer);

        return [
            'variant'   => 'no_loan',
            'title'     => __('borrower.dashboard.hero.no_loan_title'),
            'subtitle'  => ($requirements['can_apply'] ?? false)
                ? __('borrower.dashboard.hero.no_loan_subtitle_ready')
                : __('borrower.apply.kyc_incomplete_hint'),
            'amount'    => null,
            'meta'      => null,
            'cta_label' => __('borrower.dashboard.hero.apply_now'),
            'cta_url'   => route('site.borrower.loan-products'),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Repayment;
use App\Services\Grades\GradeBenefitService;
use App\Services\Plus\PlusService;
use Illuminate\Support\Carbon;

class BorrowerFinancialSnapshotService
{
    public function __construct(
        private readonly ActiveLoanServicingService $servicing,
        private readonly MemberEngagementService $engagement,
        private readonly GradeBenefitService $benefits,
        private readonly PlusService $plus,
    ) {}

    /** @return array<string, mixed> */
    public function forCustomer(Customer $customer, ?Loan $activeLoan = null): array
    {
        $activeLoan ??= Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring'])
            ->latest('disbursement_date')
            ->first();

        $locale = app()->getLocale() === 'sw' ? 'sw' : 'en';
        $trustRaw = $this->engagement->trustScore($customer);
        $trust = $this->benefits->trustLabel((int) ($trustRaw['percent'] ?? 0), $locale);
        $plusActive = $this->plus->isActive($customer);

        $items = [
            'trust' => [
                'icon' => '✦',
                'label' => __('borrower.dashboard.snapshot.trust'),
                'value' => ($trust['percent'] ?? 0).' — '.($trust['label'] ?? ''),
                'url' => route('site.borrower.plus.home'),
            ],
        ];

        $totalRepaid = (float) Repayment::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->sum('amount');
        if ($totalRepaid > 0) {
            $items['total_repaid'] = [
                'icon' => '✅',
                'label' => __('borrower.dashboard.snapshot.total_repaid'),
                'value' => format_money($totalRepaid),
            ];
        }

        if ($activeLoan) {
            $servicing = $this->servicing->forLoan($activeLoan);
            $outstanding = (float) ($servicing['outstanding_balance'] ?? $activeLoan->outstanding_balance);
            $items['outstanding'] = [
                'icon' => '📊',
                'label' => __('borrower.dashboard.snapshot.outstanding'),
                'value' => format_money($outstanding),
                'url' => route('site.borrower.loans.show', $activeLoan),
            ];

            $nextAmount = $servicing['next_due_amount'] ?? null;
            $nextDate = $servicing['next_due_date'] ?? null;
            if ($nextAmount) {
                $nextLabel = $nextDate instanceof Carbon
                    ? $nextDate->format('d M Y')
                    : (is_string($nextDate) ? Carbon::parse($nextDate)->format('d M Y') : null);
                $items['next_payment'] = [
                    'icon' => '📅',
                    'label' => __('borrower.dashboard.snapshot.next_payment'),
                    'value' => format_money((float) $nextAmount),
                    'hint' => $nextLabel,
                    'url' => route('site.borrower.payments.create', ['loan' => $activeLoan->id]),
                ];
            }
        }

        $days = $customer->membershipDaysRemaining();
        $items['membership'] = [
            'icon' => '🛡️',
            'label' => __('borrower.dashboard.snapshot.membership'),
            'value' => $customer->isMembershipActive()
                ? __('borrower.dashboard.snapshot.membership_days', ['days' => max(0, $days)])
                : __('borrower.profile.member_inactive'),
            'status' => $customer->isMembershipActive() ? 'active' : 'inactive',
            'url' => route('site.borrower.profile', ['section' => 'membership']),
        ];

        return $items;
    }
}

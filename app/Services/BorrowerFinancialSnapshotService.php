<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Loan;
use App\Models\Repayment;
use Illuminate\Support\Carbon;

class BorrowerFinancialSnapshotService
{
    public function __construct(
        private readonly LoanQualificationService $qualification,
        private readonly LoanBalanceService $balances,
        private readonly ActiveLoanServicingService $servicing,
        private readonly BorrowerCreditLimitService $creditLimit,
    ) {}

    /** @return array<string, mixed> */
    public function forCustomer(Customer $customer, ?Loan $activeLoan = null): array
    {
        $activeLoan ??= Loan::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['active', 'disbursed', 'arrears', 'restructuring'])
            ->latest('disbursement_date')
            ->first();

        $qualification = $this->qualification->calculate($customer);
        $limits = $this->creditLimit->forCustomer($customer);
        $availableLimit = $limits['available'];

        $totalRepaid = (float) Repayment::query()
            ->whereHas('loan', fn ($q) => $q->where('customer_id', $customer->id))
            ->sum('amount');

        $servicing = $activeLoan ? $this->servicing->forLoan($activeLoan) : null;
        $outstanding = $activeLoan
            ? (float) ($servicing['outstanding_balance'] ?? $activeLoan->outstanding_balance)
            : 0.0;

        $nextAmount = null;
        $nextDate = null;
        $nextLabel = __('borrower.dashboard.snapshot.next_payment_none');

        if ($servicing && ($servicing['next_due_amount'] ?? null)) {
            $nextAmount = (float) $servicing['next_due_amount'];
            $nextDate = $servicing['next_due_date'] ?? null;
            $nextLabel = $nextDate instanceof Carbon
                ? $nextDate->format('d M Y')
                : (is_string($nextDate) ? Carbon::parse($nextDate)->format('d M Y') : '—');
        }

        $membershipActive = $customer->isMembershipActive();

        return [
            'active_loan' => [
                'icon'  => '💰',
                'label' => __('borrower.dashboard.snapshot.active_loan'),
                'value' => $activeLoan
                    ? ($activeLoan->product?->localizedName() ?? $activeLoan->loan_number)
                    : __('borrower.dashboard.snapshot.no_active_loan'),
                'url'   => $activeLoan ? route('site.borrower.loans.show', $activeLoan) : null,
            ],
            'available_limit' => [
                'icon'  => '📈',
                'label' => __('borrower.dashboard.snapshot.available_limit'),
                'value' => $limits['has_data']
                    ? format_money($availableLimit)
                    : __('borrower.dashboard.snapshot.limit_unknown'),
            ],
            'total_repaid' => [
                'icon'  => '✅',
                'label' => __('borrower.dashboard.snapshot.total_repaid'),
                'value' => format_money($totalRepaid),
            ],
            'outstanding' => [
                'icon'  => '📊',
                'label' => __('borrower.dashboard.snapshot.outstanding'),
                'value' => format_money($outstanding),
                'url'   => $activeLoan ? route('site.borrower.loans.show', $activeLoan) : null,
            ],
            'next_payment' => [
                'icon'  => '📅',
                'label' => __('borrower.dashboard.snapshot.next_payment'),
                'value' => $nextAmount !== null ? format_money($nextAmount) : '—',
                'hint'  => $nextAmount !== null ? $nextLabel : null,
                'url'   => $activeLoan ? route('site.borrower.payments.create', ['loan' => $activeLoan->id]) : null,
            ],
            'membership' => [
                'icon'  => '🛡️',
                'label' => __('borrower.dashboard.snapshot.membership'),
                'value' => $membershipActive
                    ? __('borrower.profile.member_active')
                    : __('borrower.profile.member_inactive'),
                'status' => $membershipActive ? 'active' : 'inactive',
                'url'   => route('site.borrower.profile', ['section' => 'membership']),
            ],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerGuarantor;
use App\Models\Loan;
use App\Models\LoanTopUpRequest;
use App\Models\RepaymentSchedule;
use App\Models\RestructureRequest;
use Illuminate\Support\Collection;

class GuaranteedLoanService
{
    public function __construct(
        private readonly ApplicationBorrowerStatusService $borrowerStatus,
        private readonly GuarantorAccessService $access,
    ) {}

    /** @return Collection<int, object> */
    public function linksForGuarantor(Customer $guarantor): Collection
    {
        return CustomerGuarantor::query()
            ->with([
                'customer',
                'application.product',
                'application.loan.repaymentSchedules',
                'invitation.borrower',
                'invitation.product',
                'invitation.application.product',
                'invitation.application.loan.repaymentSchedules',
            ])
            ->where('status', 'approved')
            ->whereHas('invitation', fn ($q) => $q->where('guarantor_customer_id', $guarantor->id))
            ->latest()
            ->get()
            ->map(fn (CustomerGuarantor $link) => $this->formatLink($link))
            ->values();
    }

    public function formatLink(CustomerGuarantor $link): object
    {
        $invitation = $this->access->invitationForLink($link);
        $application = $link->application ?? $invitation?->application;
        $borrower = $invitation?->borrower ?? $link->customer;
        $loan = $application?->loan;
        $product = $application?->product ?? $invitation?->product;

        $amount = (float) ($application?->requested_amount ?? $invitation?->requested_amount ?? 0);
        $progress = $this->repaymentProgress($loan);
        $servicing = $loan
            ? app(ActiveLoanServicingService::class)->forLoan($loan)
            : null;
        $appStatus = $application
            ? $this->borrowerStatus->forApplication($application)
            : ['code' => 'pending_submission', 'label' => __('borrower.guaranteed.awaiting_submission'), 'tone' => 'amber'];

        $restructure = $loan
            ? RestructureRequest::query()->where('loan_id', $loan->id)->latest()->first()
            : null;
        $topUp = $loan
            ? LoanTopUpRequest::query()->where('loan_id', $loan->id)->latest()->first()
            : null;

        return (object) [
            'link'                => $link,
            'invitation'          => $invitation,
            'borrower'            => $borrower,
            'application'         => $application,
            'loan'                => $loan,
            'product'             => $product,
            'amount'              => $amount,
            'reference'           => $application?->application_number ?? $application?->draft_reference ?? '—',
            'application_status'  => $appStatus,
            'loan_status'         => $loan?->status,
            'outstanding'         => $loan ? (float) $loan->outstanding_balance : null,
            'repaid_percent'      => $progress['percent'],
            'next_due_date'       => $loan?->next_due_date,
            'in_arrears'          => $servicing['in_arrears'] ?? ($loan && $loan->status === 'arrears'),
            'amount_in_arrears'   => $servicing['amount_in_arrears'] ?? 0,
            'days_remaining'      => $servicing['days_remaining'] ?? null,
            'servicing'           => $servicing,
            'restructure'         => $restructure,
            'top_up'              => $topUp,
            'schedule'            => $loan
                ? RepaymentSchedule::where('loan_id', $loan->id)->orderBy('installment_no')->get()
                : collect(),
        ];
    }

    /** @return array{percent: float, paid: float, outstanding: float} */
    public function repaymentProgress(?Loan $loan): array
    {
        if (! $loan) {
            return ['percent' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0];
        }

        $principal = (float) $loan->principal_amount;
        $outstanding = (float) $loan->outstanding_balance;
        $paid = max(0, $principal - $outstanding);
        $percent = $principal > 0 ? min(100, ($paid / $principal) * 100) : 0.0;

        return [
            'percent'     => round($percent, 1),
            'paid'        => $paid,
            'outstanding' => $outstanding,
        ];
    }
}

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
            ->sortBy(fn (object $row) => $row->is_terminal ? 1 : 0)
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

        $guarantorCustomer = $this->access->guarantorCustomerForLink($link);
        $profileStatus = $guarantorCustomer
            ? app(GuarantorOnboardingService::class)->guarantorProfileStatus($guarantorCustomer)
            : ['met' => true, 'percent' => 100, 'next_url' => null];
        $needsGuarantorProfile = ! ($profileStatus['met'] ?? false)
            && in_array((string) ($application?->status ?? ''), ['awaiting_guarantor', 'submitted', 'pending'], true);
        $appCode = (string) ($appStatus['code'] ?? '');
        $isTerminal = in_array($appCode, ['rejected', 'withdrawn', 'offer_declined', 'closed'], true)
            || in_array((string) ($loan?->status ?? ''), ['closed', 'written_off'], true);

        $stageLabel = match (true) {
            $needsGuarantorProfile => __('borrower.guaranteed.waiting_on_your_profile'),
            $appCode === 'awaiting_guarantor' => __('borrower.guaranteed.waiting_on_guarantor_step'),
            default => $appStatus['label'] ?? '—',
        };

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
            'stage_label'         => $stageLabel,
            'needs_guarantor_profile' => $needsGuarantorProfile,
            'profile_percent'     => (int) ($profileStatus['percent'] ?? 0),
            'profile_url'         => $profileStatus['next_url'] ?? route('site.borrower.profile'),
            'is_terminal'         => $isTerminal,
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

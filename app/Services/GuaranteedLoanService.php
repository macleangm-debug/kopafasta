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

    /** All approved guarantee links for this member (tracking + disbursed). */
    public function linksForGuarantor(Customer $guarantor): Collection
    {
        return $this->approvedLinksQuery($guarantor)
            ->map(fn (CustomerGuarantor $link) => $this->formatLink($link))
            ->sortBy(fn (object $row) => $row->is_terminal ? 1 : 0)
            ->values();
    }

    /** Accepted guarantees still in application / underwriting (not yet disbursed). */
    public function trackingForGuarantor(Customer $guarantor): Collection
    {
        return $this->linksForGuarantor($guarantor)
            ->filter(fn (object $row) => ! $row->is_disbursed)
            ->values();
    }

    /** Guarantees that have become real loans (disbursed / servicing). */
    public function disbursedForGuarantor(Customer $guarantor): Collection
    {
        return $this->linksForGuarantor($guarantor)
            ->filter(fn (object $row) => $row->is_disbursed)
            ->values();
    }

    /** @return Collection<int, CustomerGuarantor> */
    private function approvedLinksQuery(Customer $guarantor): Collection
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
            ->get();
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

        $appCode = (string) ($appStatus['code'] ?? '');
        $isDisbursed = $loan !== null;
        $isTerminal = in_array($appCode, ['rejected', 'withdrawn', 'offer_declined', 'closed'], true)
            || in_array((string) ($loan?->status ?? ''), ['closed', 'written_off'], true);

        // Any accepted, not-yet-disbursed guarantee stays blocked on the guarantor's
        // own profile until it is 100% — not only when the app is in awaiting_guarantor.
        $needsGuarantorProfile = ! ($profileStatus['met'] ?? false) && ! $isDisbursed && ! $isTerminal;
        $awaitingBorrowerSubmit = $appCode === 'pending_submission' || $application === null;

        $pendingHint = match (true) {
            $needsGuarantorProfile && $awaitingBorrowerSubmit => __('borrower.guaranteed.pending_hint_profile_and_borrower', [
                'percent' => (int) ($profileStatus['percent'] ?? 0),
            ]),
            $needsGuarantorProfile => __('borrower.guaranteed.pending_hint_profile', [
                'percent' => (int) ($profileStatus['percent'] ?? 0),
            ]),
            $awaitingBorrowerSubmit => __('borrower.guaranteed.pending_hint_borrower_submit'),
            $appCode === 'awaiting_guarantor' => __('borrower.guaranteed.pending_hint_guarantor_hold'),
            $appCode === 'documents_requested' => __('borrower.guaranteed.pending_hint_documents'),
            ! $isDisbursed && ! $isTerminal => __('borrower.guaranteed.pending_hint_underwriting'),
            default => null,
        };

        $stageLabel = match (true) {
            $needsGuarantorProfile => __('borrower.guaranteed.waiting_on_your_profile'),
            $awaitingBorrowerSubmit => __('borrower.guaranteed.awaiting_submission'),
            $appCode === 'awaiting_guarantor' => __('borrower.guaranteed.waiting_on_guarantor_step'),
            default => $appStatus['label'] ?? '—',
        };

        return (object) [
            'link'                    => $link,
            'invitation'              => $invitation,
            'borrower'                => $borrower,
            'application'             => $application,
            'loan'                    => $loan,
            'product'                 => $product,
            'amount'                  => $amount,
            'reference'               => $application?->application_number
                ?? $application?->draft_reference
                ?? ($invitation?->short_code ? strtoupper((string) $invitation->short_code) : '—'),
            'application_status'      => $appStatus,
            'stage_label'             => $stageLabel,
            'pending_hint'            => $pendingHint,
            'needs_guarantor_profile' => $needsGuarantorProfile,
            'profile_percent'         => (int) ($profileStatus['percent'] ?? 0),
            'profile_url'             => $profileStatus['next_url'] ?? route('site.borrower.profile'),
            'is_terminal'             => $isTerminal,
            'is_disbursed'            => $isDisbursed,
            'loan_status'             => $loan?->status,
            'outstanding'             => $loan ? (float) $loan->outstanding_balance : null,
            'repaid_percent'          => $progress['percent'],
            'next_due_date'           => $loan?->next_due_date,
            'in_arrears'              => $servicing['in_arrears'] ?? ($loan && $loan->status === 'arrears'),
            'amount_in_arrears'       => $servicing['amount_in_arrears'] ?? 0,
            'days_remaining'          => $servicing['days_remaining'] ?? null,
            'servicing'               => $servicing,
            'restructure'             => $restructure,
            'top_up'                  => $topUp,
            'schedule'                => $loan
                ? RepaymentSchedule::where('loan_id', $loan->id)->orderBy('installment_no')->get()
                : collect(),
        ];
    }

    /**
     * Timeline for the guarantor detail page — application pipeline, or a
     * simple invitation path when the borrower has not submitted yet.
     *
     * @return array{percent: int, steps: list<array{key: string, label: string, complete: bool, current: bool}>}
     */
    public function progressTimeline(object $row): array
    {
        if ($row->application) {
            $timeline = $this->borrowerStatus->timeline($row->application);

            if (($row->needs_guarantor_profile ?? false) && ! empty($timeline['steps'])) {
                foreach ($timeline['steps'] as &$step) {
                    $step['current'] = false;
                }
                unset($step);

                array_unshift($timeline['steps'], [
                    'key'      => 'guarantor_profile',
                    'label'    => __('borrower.guaranteed.timeline_your_profile'),
                    'complete' => false,
                    'current'  => true,
                ]);
                $timeline['percent'] = min(25, (int) ($timeline['percent'] ?? 0));
            }

            return $timeline;
        }

        $profileDone = ! ($row->needs_guarantor_profile ?? false);

        return [
            'percent' => $profileDone ? 35 : 15,
            'steps'   => [
                [
                    'key'      => 'accepted',
                    'label'    => __('borrower.guaranteed.timeline_accepted'),
                    'complete' => true,
                    'current'  => false,
                ],
                [
                    'key'      => 'guarantor_profile',
                    'label'    => __('borrower.guaranteed.timeline_your_profile'),
                    'complete' => $profileDone,
                    'current'  => ! $profileDone,
                ],
                [
                    'key'      => 'borrower_submit',
                    'label'    => __('borrower.guaranteed.timeline_borrower_submit'),
                    'complete' => false,
                    'current'  => $profileDone,
                ],
                [
                    'key'      => 'review',
                    'label'    => __('borrower.guaranteed.timeline_review'),
                    'complete' => false,
                    'current'  => false,
                ],
                [
                    'key'      => 'disbursed',
                    'label'    => __('borrower.guaranteed.timeline_disbursed'),
                    'complete' => false,
                    'current'  => false,
                ],
            ],
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

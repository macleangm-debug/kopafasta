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
        $isTerminal = in_array($appCode, ['rejected', 'withdrawn', 'offer_declined', 'closed', 'expired'], true)
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

        $deadline = $this->deadlinePayload($application, $invitation, $needsGuarantorProfile);

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
            'deadline_label'          => $deadline['label'],
            'deadline_days_left'      => $deadline['days_left'],
            'deadline_date'           => $deadline['date'] ?? null,
            'deadline_urgent'         => $deadline['urgent'],
            'deadline_expired'        => $deadline['expired'],
            'servicing'               => $servicing,
            'restructure'             => $restructure,
            'top_up'                  => $topUp,
            'schedule'                => $loan
                ? RepaymentSchedule::where('loan_id', $loan->id)->orderBy('installment_no')->get()
                : collect(),
        ];
    }

    /**
     * Guarantor-facing status checklist — facts only, no promised disbursement.
     * Profile and borrower submission can advance independently.
     *
     * @return array{percent: int, steps: list<array{key: string, label: string, complete: bool, current: bool, hint?: string}>}
     */
    public function progressTimeline(object $row): array
    {
        $needsProfile = (bool) ($row->needs_guarantor_profile ?? false);
        $profileDone = ! $needsProfile;
        $appCode = (string) ($row->application_status['code'] ?? '');
        $isDisbursed = (bool) ($row->is_disbursed ?? false);
        $isTerminal = (bool) ($row->is_terminal ?? false);
        $borrowerSubmitted = $row->application !== null
            && ! in_array($appCode, ['pending_submission', ''], true);

        $steps = [
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
                'current'  => $needsProfile,
            ],
            [
                'key'      => 'borrower_submit',
                'label'    => $borrowerSubmitted
                    ? __('borrower.guaranteed.timeline_borrower_submitted')
                    : __('borrower.guaranteed.timeline_borrower_submit'),
                'complete' => $borrowerSubmitted,
                'current'  => false,
            ],
        ];

        if ($isDisbursed) {
            $steps[] = [
                'key'      => 'review',
                'label'    => __('borrower.guaranteed.timeline_review'),
                'complete' => true,
                'current'  => false,
            ];
            $steps[] = [
                'key'      => 'disbursed',
                'label'    => __('borrower.guaranteed.timeline_disbursed'),
                'complete' => true,
                'current'  => false,
            ];
        } elseif ($isTerminal) {
            $outcomeLabel = match ($appCode) {
                'rejected' => __('borrower.guaranteed.timeline_outcome_rejected'),
                'withdrawn', 'offer_declined' => __('borrower.guaranteed.timeline_outcome_withdrawn'),
                'expired' => __('borrower.guaranteed.timeline_outcome_expired'),
                default => __('borrower.guaranteed.timeline_outcome_closed'),
            };
            $steps[] = [
                'key'      => 'outcome',
                'label'    => $outcomeLabel,
                'complete' => true,
                'current'  => false,
            ];
        } else {
            $readyForReview = $profileDone && $borrowerSubmitted;
            $steps[] = [
                'key'      => 'review',
                'label'    => __('borrower.guaranteed.timeline_review'),
                'complete' => false,
                'current'  => $readyForReview,
            ];
            $steps[] = [
                'key'      => 'decision',
                'label'    => __('borrower.guaranteed.timeline_decision'),
                'complete' => false,
                'current'  => false,
            ];
        }

        $completeCount = collect($steps)->where('complete', true)->count();
        $percent = (int) round(($completeCount / max(1, count($steps))) * 100);

        return [
            'percent' => $percent,
            'steps'   => $steps,
        ];
    }

    /**
     * @return array{label: ?string, days_left: ?int, urgent: bool, expired: bool, date: ?string}
     */
    private function deadlinePayload(?\App\Models\LoanApplication $application, $invitation, bool $needsGuarantorProfile): array
    {
        $empty = ['label' => null, 'days_left' => null, 'urgent' => false, 'expired' => false, 'date' => null];

        if ($application) {
            $progress = app(GuarantorDeadlineService::class)->progress($application);
            if (! empty($progress['label']) && ($needsGuarantorProfile || ($application->status === 'awaiting_guarantor') || ($progress['expired'] ?? false))) {
                $daysLeft = $progress['days_left'];
                $date = $progress['date']
                    ?? optional($progress['deadline_at'])->timezone(config('app.timezone'))?->format('d M Y');

                $label = match (true) {
                    ($progress['expired'] ?? false) => __('borrower.guaranteed.deadline_expired_for_you'),
                    $needsGuarantorProfile && $date => __('borrower.guaranteed.deadline_for_you', [
                        'days' => max(0, (int) ($daysLeft ?? 0)),
                        'date' => $date,
                    ]),
                    default => $progress['label'],
                };

                return [
                    'label'     => $label,
                    'days_left' => $daysLeft,
                    'date'      => $date,
                    'urgent'    => ($progress['expired'] ?? false) || ((int) ($daysLeft ?? 99) <= 2),
                    'expired'   => (bool) ($progress['expired'] ?? false),
                ];
            }
        }

        $expiresAt = $invitation?->expires_at;
        if ($expiresAt && $needsGuarantorProfile) {
            $daysLeft = (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);
            $date = $expiresAt->timezone(config('app.timezone'))->format('d M Y');

            return [
                'label'     => $daysLeft < 0
                    ? __('borrower.guaranteed.invite_deadline_passed')
                    : __('borrower.guaranteed.invite_deadline_days_left', [
                        'days' => max(0, $daysLeft),
                        'date' => $date,
                    ]),
                'days_left' => $daysLeft,
                'date'      => $date,
                'urgent'    => $daysLeft < 0 || $daysLeft <= 2,
                'expired'   => $daysLeft < 0,
            ];
        }

        return $empty;
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

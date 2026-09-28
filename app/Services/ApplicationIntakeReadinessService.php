<?php

namespace App\Services;

use App\Models\CustomerGuarantor;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;

class ApplicationIntakeReadinessService
{
    public const STATE_DRAFT = 'draft';

    public const STATE_INITIAL_CHECK = 'submitted_initial_check';

    public const STATE_HOLD = 'initial_decision_hold';

    public const STATE_REJECTED_GATE = 'rejected_initial_gate';

    public const STATE_GUARANTOR_MISSING = 'guarantor_required_not_added';

    public const STATE_AWAITING_GUARANTOR = 'awaiting_guarantor';

    public const STATE_READY = 'ready_for_screening';

    public const STATE_SCREENING = 'screening';

    public const STATE_WITHDRAWN = 'withdrawn';

    public const CLOSED_STATUSES = ['withdrawn', 'rejected', 'expired', 'cancelled'];

    public function __construct(
        private readonly ProfileCompletionService $profile,
        private readonly ApplicationFeePaymentService $fees,
        private readonly GuarantorInvitationService $guarantors,
    ) {}

    /** @return array<string, mixed> */
    public function resolve(LoanApplication $application): array
    {
        $application->loadMissing(['customer', 'product']);
        $customer = $application->customer;
        $product = $application->product;
        $status = (string) $application->status;
        $stage = (string) ($application->current_stage ?? '');
        $closed = in_array($status, self::CLOSED_STATUSES, true) || in_array($stage, self::CLOSED_STATUSES, true);

        $profile = $customer ? $this->profile->completionSummary($customer) : ['percent' => 0, 'actionable' => [], 'remaining' => []];
        $borrowerComplete = $customer ? $this->profile->isFullyComplete($customer) : false;
        $submitted = filled($application->submitted_at) && ! $application->isPreSubmit();
        $guarantorRequired = (bool) ($product?->requires_guarantor);
        $nomination = $this->guarantorNomination($application);
        $gate = $this->initialGate($application);
        $ready = ! $closed
            && $borrowerComplete
            && filled($application->submitted_at)
            && ($gate['result'] ?? null) === 'passed'
            && (! $guarantorRequired || ($nomination['progress'] ?? '') === 'completed');

        $state = $this->deriveState($application, $closed, $ready, $guarantorRequired, $nomination, $gate);
        $draftReason = $this->draftReason($application, $borrowerComplete, $nomination, $product);

        return [
            'state' => $state,
            'status' => $status,
            'stage' => $stage,
            'closed' => $closed,
            'borrower_complete' => $borrowerComplete,
            'borrower_submitted' => filled($application->submitted_at) && ! $application->isPreSubmit(),
            'profile_percent' => (int) ($profile['percent'] ?? 0),
            'profile_gaps' => collect($profile['actionable'] ?? [])->pluck('label')->filter()->values()->all(),
            'guarantor_required' => $guarantorRequired,
            'guarantor' => $nomination,
            'initial_gate' => $gate,
            'ready_for_screening' => $ready,
            'draft_reason' => $draftReason,
            'next_action' => $this->nextAction($state, $nomination, $draftReason),
            'responsible' => $this->responsible($state),
        ];
    }

    /** @return array<string, mixed> */
    public function resolveDraft(LoanApplicationDraft $draft): array
    {
        $customer = $draft->customer;
        $product = $draft->product ?? LoanProduct::query()->find($draft->loan_product_id);
        $profile = $customer ? $this->profile->completionSummary($customer) : ['percent' => 0, 'actionable' => []];
        $feeOk = $customer && $product && $this->fees->isSatisfiedFor($customer, $product, $draft->payload ?? []);
        $nominated = $this->draftHasNomination($draft);
        $guarantorRequired = (bool) ($product?->requires_guarantor);
        $borrowerComplete = $customer ? $this->profile->isFullyComplete($customer) : false;
        $reason = ! $borrowerComplete
            ? 'borrower_incomplete'
            : (! $nominated && $guarantorRequired
                ? 'guarantor_not_nominated'
                : (! $feeOk ? 'fee_pending' : 'submission_not_confirmed'));

        return [
            'state' => self::STATE_DRAFT,
            'borrower_complete' => $borrowerComplete,
            'profile_gaps' => collect($profile['actionable'] ?? [])->pluck('label')->filter()->values()->all(),
            'guarantor_required' => $guarantorRequired,
            'guarantor_nominated' => $nominated,
            'fee_satisfied' => $feeOk,
            'draft_reason' => $reason,
            'can_submit' => $borrowerComplete && $feeOk && (! $guarantorRequired || $nominated),
            'next_action' => $reason,
            'responsible' => 'borrower',
        ];
    }

    public function displayStatus(LoanApplication $application): string
    {
        $status = (string) $application->status;
        if (in_array($status, self::CLOSED_STATUSES, true)) {
            return $status;
        }

        return $this->resolve($application)['state'];
    }

    public function displayStage(LoanApplication $application): ?string
    {
        if (in_array((string) $application->status, self::CLOSED_STATUSES, true)) {
            return (string) $application->status;
        }

        return (string) ($application->current_stage ?? '');
    }

    /** @return array<string, mixed> */
    public function guarantorNomination(LoanApplication $application): array
    {
        if (! $application->product?->requires_guarantor) {
            return ['progress' => 'not_required', 'nominated' => false, 'invitation' => null];
        }

        $invite = GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->latest('id')
            ->first();
        $link = CustomerGuarantor::query()
            ->where('loan_application_id', $application->id)
            ->latest('id')
            ->first();

        if (! $invite && ! $link) {
            return ['progress' => 'not_nominated', 'nominated' => false, 'invitation' => null];
        }

        $progress = match ((string) ($invite?->status ?? $link?->status ?? '')) {
            'declined', 'rejected' => 'declined',
            'expired' => 'expired',
            'accepted', 'approved' => $this->guarantors->hasReadyGuarantor($application) ? 'completed' : 'profile_incomplete',
            'pending' => data_get($application->screening_payload, 'intake.guarantor_invited_at') ? 'invited' : 'nominated',
            default => 'nominated',
        };

        return [
            'progress' => $progress,
            'nominated' => true,
            'invitation' => $invite,
            'name' => $invite?->invitee_name,
            'phone' => $invite?->contact,
            'status' => $invite?->status,
            'created_at' => $invite?->created_at,
        ];
    }

    /** @return array{result: string, reason: ?string, release_at: ?string} */
    public function initialGate(LoanApplication $application): array
    {
        if ($application->isPreSubmit()) {
            return ['result' => 'not_run', 'reason' => null, 'release_at' => null];
        }

        $stored = data_get($application->screening_payload, 'intake.initial_gate');
        if (is_array($stored) && filled($stored['result'] ?? null)) {
            return [
                'result' => (string) $stored['result'],
                'reason' => $stored['reason'] ?? null,
                'release_at' => $stored['feedback_release_at'] ?? null,
            ];
        }

        $capacity = data_get($application->screening_payload, 'capacity_auto_reject.status');
        if ($capacity === CapacityAutoRejectService::STATUS_PENDING) {
            return ['result' => 'failed', 'reason' => 'capacity_hold', 'release_at' => data_get($application->screening_payload, 'capacity_auto_reject.auto_reject_at')];
        }
        if ($capacity === CapacityAutoRejectService::STATUS_FIRED) {
            return ['result' => 'failed', 'reason' => 'capacity_rejected', 'release_at' => null];
        }

        if (in_array((string) $application->current_stage, ['screening', 'credit_appraisal', 'pre_approval', 'ready_for_screening'], true)
            || (string) $application->status === 'awaiting_guarantor') {
            return ['result' => 'passed', 'reason' => null, 'release_at' => null];
        }

        return ['result' => filled($application->submitted_at) ? 'pending' : 'not_run', 'reason' => null, 'release_at' => null];
    }

    public function draftHasNomination(LoanApplicationDraft $draft): bool
    {
        $payload = $draft->payload ?? [];
        $internal = $payload['internal_guarantor'] ?? [];
        $external = $payload['external_guarantor'] ?? [];
        $mode = $payload['form']['guarantor_mode'] ?? $payload['guarantor_mode'] ?? null;

        return in_array($mode, ['internal', 'external', 'previous'], true)
            || filled($internal['invitation_id'] ?? null)
            || filled($external['invitation_id'] ?? null);
    }

    private function deriveState(
        LoanApplication $application,
        bool $closed,
        bool $ready,
        bool $guarantorRequired,
        array $nomination,
        array $gate,
    ): string {
        $status = (string) $application->status;
        $stage = (string) ($application->current_stage ?? '');

        if ($status === 'withdrawn' || $stage === 'withdrawn') {
            return self::STATE_WITHDRAWN;
        }
        if ($closed) {
            return $status === 'rejected' || $stage === 'rejected_initial_gate'
                ? self::STATE_REJECTED_GATE
                : $status;
        }
        if ($application->isPreSubmit()) {
            return self::STATE_DRAFT;
        }
        if ($stage === self::STATE_HOLD || ($gate['result'] ?? null) === 'failed' && $status !== 'rejected') {
            return self::STATE_HOLD;
        }
        if ($ready || $stage === self::STATE_READY) {
            return self::STATE_READY;
        }
        if ($status === 'awaiting_guarantor' || $stage === 'awaiting_guarantor') {
            return self::STATE_AWAITING_GUARANTOR;
        }
        if ($stage === self::STATE_GUARANTOR_MISSING || ($guarantorRequired && ($nomination['progress'] ?? '') === 'not_nominated' && filled($application->submitted_at))) {
            return self::STATE_GUARANTOR_MISSING;
        }
        if ($stage === self::STATE_INITIAL_CHECK) {
            return self::STATE_INITIAL_CHECK;
        }
        if (in_array($stage, ['screening', 'credit_appraisal', 'pre_approval'], true)) {
            return self::STATE_SCREENING;
        }

        return $stage !== '' ? $stage : self::STATE_INITIAL_CHECK;
    }

    private function draftReason(
        LoanApplication $application,
        bool $borrowerComplete,
        array $nomination,
        ?LoanProduct $product,
    ): ?string {
        if (! $application->isPreSubmit()) {
            return null;
        }
        if (! $borrowerComplete) {
            return 'borrower_incomplete';
        }
        if ($product?->requires_guarantor && ($nomination['progress'] ?? '') === 'not_nominated') {
            return 'guarantor_not_nominated';
        }
        if (! in_array((string) $application->application_fee_status, ['paid', 'waived', 'charged'], true)) {
            return 'fee_pending';
        }
        if (filled($application->submitted_at)) {
            return 'status_transition_exception';
        }

        return 'submission_not_confirmed';
    }

    /** @return array{label: string, actor: string} */
    private function nextAction(string $state, array $nomination, ?string $draftReason): array
    {
        return match ($state) {
            self::STATE_DRAFT => ['label' => $draftReason ?? 'complete_application', 'actor' => 'borrower'],
            self::STATE_INITIAL_CHECK => ['label' => 'initial_eligibility_check', 'actor' => 'system'],
            self::STATE_HOLD => ['label' => 'await_feedback_release', 'actor' => 'staff'],
            self::STATE_GUARANTOR_MISSING => ['label' => 'add_guarantor', 'actor' => 'borrower'],
            self::STATE_AWAITING_GUARANTOR => ['label' => $nomination['progress'] ?? 'complete_guarantor', 'actor' => 'guarantor'],
            self::STATE_READY => ['label' => 'send_to_screening', 'actor' => 'staff'],
            default => ['label' => $state, 'actor' => 'staff'],
        };
    }

    private function responsible(string $state): string
    {
        return match ($state) {
            self::STATE_DRAFT, self::STATE_GUARANTOR_MISSING => 'borrower',
            self::STATE_AWAITING_GUARANTOR => 'guarantor',
            self::STATE_INITIAL_CHECK => 'system',
            default => 'staff',
        };
    }

    /** @return array<string, int> */
    public function intakeCounts(): array
    {
        $base = LoanApplication::query();

        return [
            'initial_check' => (clone $base)->whereNotIn('status', self::CLOSED_STATUSES)
                ->whereIn('current_stage', [self::STATE_INITIAL_CHECK, self::STATE_HOLD])->count(),
            'awaiting_guarantor' => (clone $base)->whereNotIn('status', self::CLOSED_STATUSES)
                ->where(fn ($q) => $q->where('status', 'awaiting_guarantor')->orWhere('current_stage', self::STATE_AWAITING_GUARANTOR))->count(),
            'ready_for_screening' => (clone $base)->whereNotIn('status', self::CLOSED_STATUSES)
                ->where('current_stage', self::STATE_READY)->count(),
            'drafts' => app(LoanApplicationDraftService::class)->countIncomplete(),
            'closed' => (clone $base)->whereIn('status', ['withdrawn', 'rejected'])->count(),
        ];
    }

    public function draftReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'borrower_incomplete' => __('admin.intake.draft_borrower_incomplete'),
            'guarantor_not_nominated' => __('admin.intake.draft_guarantor_not_nominated'),
            'fee_pending' => __('admin.intake.draft_fee_pending'),
            'submission_not_confirmed' => __('admin.intake.draft_submission_not_confirmed'),
            'status_transition_exception' => __('admin.intake.draft_status_exception'),
            default => __('admin.intake.draft_unknown'),
        };
    }
}

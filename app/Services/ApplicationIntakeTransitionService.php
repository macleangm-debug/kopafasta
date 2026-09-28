<?php

namespace App\Services;

use App\Models\ApplicationStageHistory;
use App\Models\AuditLog;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ApplicationIntakeTransitionService
{
    public function __construct(
        private readonly ApplicationIntakeReadinessService $readiness,
        private readonly CreditEligibilityPolicyService $eligibility,
        private readonly GuarantorInvitationService $guarantors,
        private readonly GuarantorDeadlineService $deadlines,
        private readonly CapacityAutoRejectService $capacity,
        private readonly UnderwritingSettingsService $settings,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function afterBorrowerSubmit(LoanApplication $application): LoanApplication
    {
        $application->refresh()->loadMissing(['customer', 'product']);
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['intake'] = array_merge((array) ($payload['intake'] ?? []), [
            'submitted_at' => now()->toIso8601String(),
        ]);

        $eval = $this->eligibility->evaluate($application, verified: false);

        if ($this->borrowerFailedFirstGate($eval)) {
            $application->update(['screening_payload' => $payload]);

            return $this->parkFailedFirstGate($application->fresh(['customer', 'product']), $eval, notify: true);
        }

        $payload['intake']['initial_gate'] = [
            'result' => 'passed',
            'reason' => null,
            'evaluated_at' => now()->toIso8601String(),
        ];
        $application->update(['screening_payload' => $payload]);

        return $this->continueAfterPassedGate($application->fresh(['customer', 'product']));
    }

    public function borrowerFailedFirstGate(array $eval): bool
    {
        return ($eval['application_action'] ?? null) === CreditEligibilityPolicyService::ACTION_PENDING_REJECTION
            && str_starts_with((string) ($eval['reason'] ?? ''), 'Borrower failed');
    }

    public function parkFailedFirstGate(LoanApplication $application, ?array $eval = null, bool $notify = true): LoanApplication
    {
        $application->refresh()->loadMissing(['customer', 'product']);
        $eval ??= $this->eligibility->evaluate($application, verified: false);
        $hours = max(1, $this->settings->capacityAutoRejectDelayHours());
        $releaseAt = now()->addHours($hours);
        $alreadyParked = $this->capacity->isPending($application);

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['intake'] = array_merge((array) ($payload['intake'] ?? []), [
            'initial_gate' => array_merge((array) ($payload['intake']['initial_gate'] ?? []), [
                'result' => 'failed',
                'reason' => $eval['reason'] ?? data_get($payload, 'intake.initial_gate.reason') ?? 'initial_eligibility_failed',
                'rules' => $eval['participants'] ?? data_get($payload, 'intake.initial_gate.rules') ?? [],
                'evaluated_at' => data_get($payload, 'intake.initial_gate.evaluated_at') ?: now()->toIso8601String(),
            ]),
        ]);

        $application->update([
            'status' => 'submitted',
            'current_stage' => $alreadyParked
                ? ApplicationIntakeReadinessService::STATE_HOLD
                : ApplicationIntakeReadinessService::STATE_INITIAL_CHECK,
            'guarantor_deadline_at' => null,
            'screening_payload' => $payload,
        ]);

        $parked = $this->capacity->evaluateAndPark($application->fresh(['customer', 'product']));
        $application = $application->fresh();
        $payload = is_array($application->screening_payload) ? $application->screening_payload : $payload;
        $payload['intake']['initial_gate']['feedback_release_at'] = data_get($parked, 'auto_reject_at')
            ?? data_get($payload, 'intake.initial_gate.feedback_release_at')
            ?? $releaseAt->toIso8601String();
        $payload['intake']['initial_gate']['parked_at'] = data_get($parked, 'parked_at')
            ?? data_get($payload, 'intake.initial_gate.parked_at');
        $payload['intake']['initial_gate']['settings_key'] = data_get($parked, 'settings_key')
            ?: 'underwriting.capacity_auto_reject_delay_hours';

        $fromStage = (string) $application->current_stage;
        $application->update([
            'status' => 'submitted',
            'current_stage' => ApplicationIntakeReadinessService::STATE_HOLD,
            'guarantor_deadline_at' => null,
            'screening_payload' => $payload,
        ]);

        if (! $alreadyParked) {
            $this->record($application, ApplicationIntakeReadinessService::STATE_HOLD, 'Borrower failed initial eligibility. Parked for review. Guarantor work is not the next action.', $fromStage);
            if ($notify) {
                $this->notifyBorrower($application, 'intake_received', 'borrower.intake.received_title', 'borrower.intake.received_hold_body');
            }
        }

        return $application->fresh();
    }

    public function dispatchGuarantorInvitation(LoanApplication $application): void
    {
        if (data_get($application->screening_payload, 'intake.guarantor_invited_at')) {
            return;
        }

        if (data_get($application->screening_payload, 'intake.initial_gate.result') === 'failed') {
            return;
        }

        $park = data_get($application->screening_payload, 'capacity_auto_reject.status');
        if (in_array($park, [CapacityAutoRejectService::STATUS_PENDING, CapacityAutoRejectService::STATUS_FIRED], true)) {
            return;
        }

        if (in_array((string) $application->status, ['rejected', 'withdrawn', 'cancelled', 'expired'], true)) {
            return;
        }

        $invitation = GuarantorInvitation::query()
            ->where('loan_application_id', $application->id)
            ->latest('id')
            ->first();
        if (! $invitation || ! $application->customer) {
            return;
        }

        try {
            if ($invitation->type === 'internal' && $invitation->guarantor_customer_id) {
                $member = $invitation->guarantorCustomer;
                $link = $invitation->customerGuarantor;
                if ($member && $link) {
                    $this->guarantors->notifyInternalGuarantorRequest($application->customer, $member, $link, $invitation, $application);
                }
            } else {
                $this->guarantors->notifyExternalInvitation($application->customer, $invitation, (string) $invitation->invitee_name);
            }
            $this->guarantors->notifyBorrowerInvitationSent($application->customer, $invitation, (string) $invitation->invitee_name);
        } catch (\Throwable $e) {
            report($e);
        }

        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['intake']['guarantor_invited_at'] = now()->toIso8601String();
        $application->update(['screening_payload' => $payload]);
    }

    public function releaseToReadyForScreening(LoanApplication $application): bool
    {
        $onHold = (string) $application->status === 'awaiting_guarantor'
            || (string) $application->current_stage === 'awaiting_guarantor';
        if (! $onHold || $this->guarantors->guarantorHoldBlocker($application) !== null) {
            return false;
        }
        if ($this->capacity->isPending($application)
            || data_get($application->screening_payload, 'intake.initial_gate.result') === 'failed') {
            return false;
        }

        $fromStage = (string) ($application->current_stage ?: 'awaiting_guarantor');
        $fromStatus = (string) $application->status;
        $application->update([
            'status' => 'submitted',
            'current_stage' => ApplicationIntakeReadinessService::STATE_READY,
            'guarantor_deadline_at' => null,
        ]);
        $this->record($application, ApplicationIntakeReadinessService::STATE_READY, 'Guarantor completed. Ready for screening.', $fromStage, $fromStatus);
        $this->notifyBorrower($application, 'intake_ready', 'borrower.intake.ready_title', 'borrower.intake.ready_body');

        return true;
    }

    public function sendToScreening(LoanApplication $application, ?User $actor = null): LoanApplication
    {
        if (! $this->readiness->canStartScreening($application)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'screening' => 'Only a Ready for Screening application can start Credit Screening.',
            ]);
        }

        $fromStage = (string) $application->current_stage;
        $application->update([
            'status' => 'submitted',
            'current_stage' => 'screening',
        ]);
        $this->record($application, 'screening', 'Staff sent application to credit screening.', $fromStage, 'submitted', $actor);
        $this->capacity->evaluateAndPark($application->fresh(['customer', 'product']));

        return $application->fresh();
    }

    public function overrideHold(LoanApplication $application, User $actor, string $reason): LoanApplication
    {
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['intake']['initial_gate'] = array_merge((array) ($payload['intake']['initial_gate'] ?? []), [
            'result' => 'passed',
            'overridden_at' => now()->toIso8601String(),
            'overridden_by' => $actor->id,
            'override_reason' => $reason,
        ]);
        $application->update([
            'status' => 'submitted',
            'current_stage' => ApplicationIntakeReadinessService::STATE_INITIAL_CHECK,
            'screening_payload' => $payload,
        ]);
        if ($this->capacity->isPending($application->fresh())) {
            $this->capacity->cancel($application->fresh(), $actor, $reason);
        }
        $this->record($application, ApplicationIntakeReadinessService::STATE_INITIAL_CHECK, 'Staff overrode initial-gate hold: '.$reason, ApplicationIntakeReadinessService::STATE_HOLD, 'submitted', $actor);

        return $this->continueAfterPassedGate($application->fresh(['customer', 'product']));
    }

    public function continueAfterPassedGate(LoanApplication $application): LoanApplication
    {
        $required = (bool) $application->product?->requires_guarantor;
        $nomination = $this->readiness->guarantorNomination($application);

        if ($required && ! ($nomination['nominated'] ?? false)) {
            $application->update([
                'status' => 'submitted',
                'current_stage' => ApplicationIntakeReadinessService::STATE_GUARANTOR_MISSING,
            ]);
            $this->record($application, ApplicationIntakeReadinessService::STATE_GUARANTOR_MISSING, 'Submitted without a nominated guarantor.');
            $this->notifyBorrower($application, 'intake_received', 'borrower.intake.received_title', 'borrower.intake.add_guarantor_body');

            return $application->fresh();
        }

        if ($required) {
            $this->deadlines->markAwaiting($application->fresh());
            $this->dispatchGuarantorInvitation($application->fresh(['customer', 'product']));
            $this->notifyBorrower($application, 'intake_gate_passed', 'borrower.intake.passed_title', 'borrower.intake.passed_guarantor_body');

            return $application->fresh();
        }

        $application->update([
            'status' => 'submitted',
            'current_stage' => ApplicationIntakeReadinessService::STATE_READY,
        ]);
        $this->record($application, ApplicationIntakeReadinessService::STATE_READY, 'Initial eligibility passed. Ready for screening.');
        $this->notifyBorrower($application, 'intake_ready', 'borrower.intake.ready_title', 'borrower.intake.ready_body');

        return $application->fresh();
    }

    public function cancelIncompleteByBorrower(LoanApplication $application): ?LoanApplication
    {
        $application->refresh()->loadMissing(['customer', 'product']);
        if (! $this->readiness->borrowerMayCancel($application)) {
            return null;
        }

        $fromStage = (string) $application->current_stage;
        $fromStatus = (string) $application->status;
        $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
        $payload['intake'] = array_merge((array) ($payload['intake'] ?? []), [
            'cancelled_by_borrower_at' => now()->toIso8601String(),
            'cancelled_from_status' => $fromStatus,
            'cancelled_from_stage' => $fromStage,
        ]);

        $application->update([
            'status' => 'withdrawn',
            'current_stage' => 'withdrawn',
            'guarantor_deadline_at' => null,
            'screening_payload' => $payload,
        ]);

        $application->documentRequests()
            ->whereIn('status', ['pending', 'rejected', 'uploaded'])
            ->update(['status' => 'satisfied']);

        if ($application->customer && $application->loan_product_id) {
            app(LoanApplicationDraftService::class)
                ->discard($application->customer, (int) $application->loan_product_id);
        }

        $this->record(
            $application,
            ApplicationIntakeReadinessService::STATE_WITHDRAWN,
            'Borrower cancelled an incomplete application before submission.',
            $fromStage,
            $fromStatus,
        );

        return $application->fresh();
    }

    public function canRestoreIncompleteBorrowerCancel(LoanApplication $application): bool
    {
        return $this->withdrawalRestorePlan($application) !== null;
    }

    public function restoreIncompleteBorrowerCancelBlocker(LoanApplication $application): ?string
    {
        return $this->withdrawalRestorePlan($application) === null
            ? 'unproven_prior_state'
            : null;
    }

    /**
     * Proven reversal target for a withdrawn application, or null when Admin must not guess.
     *
     * @return array{
     *     to_status: string,
     *     to_stage: string,
     *     from_status: string,
     *     from_stage: string,
     *     is_draft: bool,
     *     withdrawal_history_id: int|null,
     *     state_label: string
     * }|null
     */
    public function withdrawalRestorePlan(LoanApplication $application): ?array
    {
        $application->refresh()->loadMissing(['customer', 'product', 'loan']);

        if ((string) $application->status !== 'withdrawn'
            || (string) $application->current_stage !== 'withdrawn') {
            return null;
        }

        if ((string) $application->offer_status === 'declined') {
            return null;
        }

        if ($application->loan
            || filled($application->offered_amount)
            || filled($application->rejection_reason_code)) {
            return null;
        }

        $rejectionNote = strtolower(trim((string) $application->rejection_reason));
        if ($rejectionNote !== '' && ! str_contains($rejectionNote, 'withdrawn')) {
            return null;
        }

        $capacityStatus = (string) data_get($application->screening_payload, 'capacity_auto_reject.status');
        if (in_array($capacityStatus, ['fired', 'pending'], true)) {
            return null;
        }

        $proven = $this->provenPreWithdrawalState($application);
        if ($proven === null) {
            return null;
        }

        [$toStatus, $toStage] = $proven;
        if (! $this->isSafeRestoreTarget($toStatus, $toStage)) {
            return null;
        }

        $openSibling = LoanApplication::query()
            ->where('customer_id', $application->customer_id)
            ->where('id', '!=', $application->id)
            ->whereNotIn('status', array_merge(LoanApplication::CLOSED_STATUSES, ['closed', 'completed', 'settled']))
            ->whereNotIn('current_stage', LoanApplication::CLOSED_STATUSES)
            ->exists();
        if ($openSibling) {
            return null;
        }

        $withdrawalEvent = ApplicationStageHistory::query()
            ->where('loan_application_id', $application->id)
            ->where('to_stage', ApplicationIntakeReadinessService::STATE_WITHDRAWN)
            ->latest('id')
            ->first();

        $isDraft = $toStatus === 'draft'
            || in_array($toStage, ['draft', ApplicationIntakeReadinessService::STATE_DRAFT], true);

        return [
            'to_status' => $toStatus,
            'to_stage' => $toStage,
            'from_status' => 'withdrawn',
            'from_stage' => 'withdrawn',
            'is_draft' => $isDraft,
            'withdrawal_history_id' => $withdrawalEvent?->id,
            'state_label' => $isDraft
                ? __('admin.intake.restore_to_draft')
                : __('admin.intake.restore_to_submitted'),
        ];
    }

    public function restoreIncompleteBorrowerCancel(
        LoanApplication $application,
        User $actor,
        string $reason,
        ?string $notes = null,
    ): LoanApplication {
        $plan = $this->withdrawalRestorePlan($application);
        if ($plan === null) {
            throw new \RuntimeException(__('admin.intake.restore_incomplete_blocked'));
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException(__('admin.intake.restore_incomplete_reason'));
        }

        $notes = trim((string) $notes);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($application, $actor, $reason, $notes, $plan) {
            $application->refresh()->loadMissing(['customer', 'product']);
            $payload = is_array($application->screening_payload) ? $application->screening_payload : [];
            $payload['intake'] = array_merge((array) ($payload['intake'] ?? []), [
                'restored_from_borrower_cancel_at' => now()->toIso8601String(),
                'restored_from_withdrawal_at' => now()->toIso8601String(),
                'restored_by' => $actor->id,
                'restore_reason' => $reason,
                'restore_notes' => $notes !== '' ? $notes : null,
                'restored_from_status' => $plan['from_status'],
                'restored_from_stage' => $plan['from_stage'],
                'restored_to_status' => $plan['to_status'],
                'restored_to_stage' => $plan['to_stage'],
                'original_withdrawal_history_id' => $plan['withdrawal_history_id'],
            ]);

            $application->update([
                'status' => $plan['to_status'],
                'current_stage' => $plan['to_stage'],
                'screening_payload' => $payload,
            ]);

            if ($plan['is_draft']) {
                app(LoanApplicationDraftService::class)
                    ->ensureResumeDraftForApplication($application->fresh(['customer', 'product']));
            }

            $this->record(
                $application->fresh(),
                $plan['to_stage'],
                'Admin restored a withdrawal. '.$reason,
                'withdrawn',
                'withdrawn',
                $actor,
            );

            $this->audit->log($actor, 'application.restore_withdrawal', $application->fresh(), [
                'status' => 'withdrawn',
                'current_stage' => 'withdrawn',
            ], [
                'status' => $plan['to_status'],
                'current_stage' => $plan['to_stage'],
                'application_number' => $application->application_number,
                'reason' => $reason,
                'notes' => $notes !== '' ? $notes : null,
                'from_state' => 'withdrawn',
                'restored_to_state' => $plan['to_status'].'/'.$plan['to_stage'],
                'original_withdrawal_history_id' => $plan['withdrawal_history_id'],
            ]);

            return $application->fresh(['customer', 'product']);
        });
    }

    /** @return array{0: string, 1: string}|null */
    private function provenPreWithdrawalState(LoanApplication $application): ?array
    {
        $event = ApplicationStageHistory::query()
            ->where('loan_application_id', $application->id)
            ->where('to_stage', ApplicationIntakeReadinessService::STATE_WITHDRAWN)
            ->whereNotNull('from_stage')
            ->where('from_stage', '!=', '')
            ->where('from_stage', '!=', ApplicationIntakeReadinessService::STATE_WITHDRAWN)
            ->latest('id')
            ->first();

        if ($event) {
            $stage = (string) $event->from_stage;

            return [$this->statusForProvenStage($stage, $application), $stage];
        }

        $fromStatus = (string) (data_get($application->screening_payload, 'intake.cancelled_from_status') ?? '');
        $fromStage = (string) (data_get($application->screening_payload, 'intake.cancelled_from_stage') ?? '');
        if ($fromStatus !== '' && $fromStage !== '') {
            return [$fromStatus, $fromStage];
        }

        $reconcile = AuditLog::query()
            ->where('auditable_id', $application->id)
            ->where('auditable_type', LoanApplication::class)
            ->where('event', 'application.intake_reconciled')
            ->latest('id')
            ->first();
        $reconcileStage = (string) data_get($reconcile?->old_values, 'current_stage');
        $reconcileStatus = (string) data_get($reconcile?->old_values, 'status');
        if ($reconcileStage !== '' && $reconcileStage !== ApplicationIntakeReadinessService::STATE_WITHDRAWN) {
            $status = $reconcileStatus !== '' && $reconcileStatus !== 'withdrawn'
                ? $reconcileStatus
                : $this->statusForProvenStage($reconcileStage, $application);

            return [$status, $reconcileStage];
        }

        return null;
    }

    private function statusForProvenStage(string $stage, LoanApplication $application): string
    {
        $fromPayload = (string) (data_get($application->screening_payload, 'intake.cancelled_from_status') ?? '');
        if ($fromPayload !== '') {
            return $fromPayload;
        }

        return match ($stage) {
            'draft', ApplicationIntakeReadinessService::STATE_DRAFT => 'draft',
            ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR, 'awaiting_guarantor' => 'awaiting_guarantor',
            'under_review', 'credit_appraisal' => 'under_review',
            default => 'submitted',
        };
    }

    private function isSafeRestoreTarget(string $status, string $stage): bool
    {
        $unsafeStages = [
            'rejected',
            ApplicationIntakeReadinessService::STATE_REJECTED_GATE,
            'approved',
            'offer_issued',
            'awaiting_offer',
            'disbursed',
            'closed',
            'cancelled',
            'pre_approval',
            'approval',
            'disbursement',
        ];
        $unsafeStatuses = [
            'rejected', 'approved', 'pre_approved', 'disbursed', 'closed', 'cancelled', 'offer_declined',
        ];

        if (in_array($stage, $unsafeStages, true) || in_array($status, $unsafeStatuses, true)) {
            return false;
        }

        return in_array($stage, [
            'draft',
            ApplicationIntakeReadinessService::STATE_DRAFT,
            'submitted',
            ApplicationIntakeReadinessService::STATE_INITIAL_CHECK,
            ApplicationIntakeReadinessService::STATE_HOLD,
            ApplicationIntakeReadinessService::STATE_GUARANTOR_MISSING,
            ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR,
            'awaiting_guarantor',
            ApplicationIntakeReadinessService::STATE_READY,
            ApplicationIntakeReadinessService::STATE_SCREENING,
            'credit_appraisal',
        ], true);
    }

    public function feedbackReleaseAt(LoanApplication $application): ?Carbon
    {
        $raw = data_get($application->screening_payload, 'intake.initial_gate.feedback_release_at');

        return filled($raw) ? Carbon::parse((string) $raw) : null;
    }

    private function record(
        LoanApplication $application,
        string $toStage,
        string $remarks,
        ?string $fromStage = null,
        ?string $fromStatus = null,
        ?User $actor = null,
    ): void {
        ApplicationStageHistory::query()->create([
            'loan_application_id' => $application->id,
            'from_stage' => $fromStage ?? $application->current_stage,
            'to_stage' => $toStage,
            'changed_by' => $actor?->id,
            'remarks' => $remarks,
        ]);
        $this->audit->log($actor, 'application.intake_transition', $application, [
            'current_stage' => $fromStage,
            'status' => $fromStatus,
        ], [
            'current_stage' => $toStage,
            'remarks' => $remarks,
        ]);
    }

    private function notifyBorrower(LoanApplication $application, string $event, string $titleKey, string $bodyKey): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $needle = '%'.$application->id.'%';
        $already = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', $event)
            ->where(function ($q) use ($needle) {
                $q->where('recipient', 'like', $needle);
                if (Schema::hasColumn('notification_logs', 'action_url')) {
                    $q->orWhere('action_url', 'like', $needle);
                }
            })
            ->exists();
        if ($already) {
            return;
        }

        $this->notifications->notifyInApp(
            $customer,
            __($bodyKey),
            'application',
            $event,
            __($titleKey),
            route('site.borrower.application', $application),
        );
    }
}

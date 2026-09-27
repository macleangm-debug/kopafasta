<?php

namespace App\Services;

use App\Models\ApplicationStageHistory;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Carbon;

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
        $failed = ($eval['application_action'] ?? null) === CreditEligibilityPolicyService::ACTION_PENDING_REJECTION
            && str_starts_with((string) ($eval['reason'] ?? ''), 'Borrower failed');
        $hours = max(1, $this->settings->capacityAutoRejectDelayHours());
        $releaseAt = now()->addHours($hours);

        if ($failed) {
            $payload['intake']['initial_gate'] = [
                'result' => 'failed',
                'reason' => $eval['reason'] ?? 'initial_eligibility_failed',
                'rules' => $eval['participants'] ?? [],
                'feedback_release_at' => $releaseAt->toIso8601String(),
                'evaluated_at' => now()->toIso8601String(),
            ];
            $application->update([
                'status' => 'submitted',
                'current_stage' => ApplicationIntakeReadinessService::STATE_HOLD,
                'screening_payload' => $payload,
            ]);
            $this->record($application, ApplicationIntakeReadinessService::STATE_HOLD, 'Borrower failed initial eligibility. Guarantor not invited.');
            $this->notifyBorrower($application, 'intake_received', 'borrower.intake.received_title', 'borrower.intake.received_hold_body');

            return $application->fresh();
        }

        $payload['intake']['initial_gate'] = [
            'result' => 'passed',
            'reason' => null,
            'evaluated_at' => now()->toIso8601String(),
        ];
        $application->update(['screening_payload' => $payload]);

        return $this->continueAfterPassedGate($application->fresh(['customer', 'product']));
    }

    public function dispatchGuarantorInvitation(LoanApplication $application): void
    {
        if (data_get($application->screening_payload, 'intake.guarantor_invited_at')) {
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

        $already = NotificationLog::query()
            ->where('customer_id', $customer->id)
            ->where('template', $event)
            ->where('action_url', 'like', '%'.$application->id.'%')
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

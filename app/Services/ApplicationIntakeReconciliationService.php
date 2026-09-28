<?php

namespace App\Services;

use App\Models\LoanApplication;
use Illuminate\Support\Collection;

class ApplicationIntakeReconciliationService
{
    public function __construct(
        private readonly ApplicationIntakeReadinessService $readiness,
        private readonly ApplicationIntakeTransitionService $transitions,
        private readonly CreditEligibilityPolicyService $eligibility,
        private readonly CapacityAutoRejectService $capacity,
        private readonly LoanApplicationDraftService $drafts,
        private readonly AuditService $audit,
    ) {}

    /**
     * Identify contradictory drafts, awaiting-guarantor files whose borrower
     * already failed Gate One, ready-for-screening files still waiting, and
     * closed rows with a stale open stage.
     *
     * @param  list<string>  $numbers
     * @return list<array<string, mixed>>
     */
    public function plan(array $numbers = []): array
    {
        $rows = $this->candidates($numbers);

        return $rows->map(fn (LoanApplication $application) => $this->proposal($application))->all();
    }

    /**
     * @param  list<string>  $numbers
     * @return list<array<string, mixed>>
     */
    public function apply(array $numbers = [], bool $notify = false): array
    {
        $applied = [];
        foreach ($this->candidates($numbers) as $application) {
            $proposal = $this->proposal($application);
            if (($proposal['action'] ?? '') === 'none') {
                $applied[] = $proposal;

                continue;
            }

            $beforeStatus = (string) $application->status;
            $beforeStage = (string) $application->current_stage;
            $this->applyProposal($application->fresh(['customer', 'product']), $proposal, $notify);
            $fresh = $application->fresh();
            $proposal['after_status'] = (string) $fresh->status;
            $proposal['after_stage'] = (string) $fresh->current_stage;
            $proposal['applied'] = true;
            $proposal['notified'] = $notify && in_array($proposal['action'], ['park', 'transition'], true);
            $this->audit->log(null, 'application.intake_reconciled', $fresh, [
                'status' => $beforeStatus,
                'current_stage' => $beforeStage,
            ], [
                'status' => $fresh->status,
                'current_stage' => $fresh->current_stage,
                'reason' => $proposal['reason'],
            ]);
            $applied[] = $proposal;
        }

        return $applied;
    }

    /**
     * @param  list<string>  $numbers
     */
    private function candidates(array $numbers): Collection
    {
        return LoanApplication::query()
            ->with(['customer', 'product'])
            ->when($numbers !== [], fn ($q) => $q->whereIn('application_number', $numbers))
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->where('status', 'draft')->whereNotNull('submitted_at');
                })->orWhere(function ($q) {
                    $q->where('status', 'awaiting_guarantor')
                        ->orWhere('current_stage', ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR);
                })->orWhere(function ($q) {
                    $q->whereIn('status', ApplicationIntakeReadinessService::CLOSED_STATUSES)
                        ->where('current_stage', 'awaiting_guarantor');
                })->orWhere(function ($q) {
                    $q->where('current_stage', ApplicationIntakeReadinessService::STATE_HOLD);
                });
            })
            ->orderBy('id')
            ->get();
    }

    /** @return array<string, mixed> */
    private function proposal(LoanApplication $application): array
    {
        $resolved = $this->readiness->resolve($application);
        $fromStatus = (string) $application->status;
        $fromStage = (string) $application->current_stage;
        $closed = in_array($fromStatus, ApplicationIntakeReadinessService::CLOSED_STATUSES, true);

        if ($closed) {
            if ($fromStage === 'awaiting_guarantor') {
                return $this->row($application, $resolved, 'clear_closed_stage', $fromStatus, $fromStatus, 'Closed application must not keep an awaiting-guarantor stage.');
            }

            return $this->row($application, $resolved, 'none', $fromStatus, $fromStage, 'Closed application — no First Gate and no guarantor work.');
        }

        if ($this->alreadyParked($application)) {
            return $this->row($application, $resolved, 'none', $fromStatus, $fromStage, 'Already Parked Screening. Leave timestamps unchanged.');
        }

        $isContradictoryDraft = $fromStatus === 'draft' && filled($application->submitted_at);
        $isAwaiting = $fromStatus === 'awaiting_guarantor' || $fromStage === 'awaiting_guarantor';

        if ($isContradictoryDraft && ! ($resolved['borrower_complete'] ?? false)) {
            if ($this->drafts->hasAlignedIncompleteDraft($application)) {
                return $this->row(
                    $application,
                    $resolved,
                    'none',
                    'draft',
                    'draft',
                    'Incomplete Application. Historical submitted_at does not override readiness. First Gate is not evaluated.',
                );
            }

            return $this->row(
                $application,
                $resolved,
                'return_to_incomplete',
                'draft',
                'draft',
                'Incomplete Application. Leave submitted/intake queues. Resume under Incomplete Applications. First Gate is not evaluated.',
            );
        }

        if (! $isContradictoryDraft && ! $isAwaiting) {
            return $this->row($application, $resolved, 'none', $fromStatus, $fromStage, 'No intake correction required.');
        }

        $eval = $this->eligibility->evaluate($application->fresh(['customer', 'product']), verified: false);
        $resolved['gate_one_action'] = $eval['application_action'] ?? null;
        $resolved['gate_one_reason'] = $eval['reason'] ?? null;

        if ($this->transitions->borrowerFailedFirstGate($eval)) {
            return $this->row(
                $application,
                $resolved,
                'park',
                'submitted',
                ApplicationIntakeReadinessService::STATE_HOLD,
                'Borrower failed Gate One. Guarantor is not the next action. Parked Screening.',
            );
        }

        $guarantor = $resolved['guarantor'] ?? [];
        $progress = (string) ($guarantor['progress'] ?? 'not_nominated');
        $required = (bool) ($resolved['guarantor_required'] ?? false);

        if ($required && $progress === 'not_nominated') {
            return $this->row($application, $resolved, 'transition', 'submitted', ApplicationIntakeReadinessService::STATE_GUARANTOR_MISSING, 'Gate One passed. Guarantor not nominated.');
        }

        if ($required && $progress === 'completed') {
            return $this->row($application, $resolved, 'transition', 'submitted', ApplicationIntakeReadinessService::STATE_READY, 'Gate One passed and guarantor complete. Ready for screening.');
        }

        if ($required && $isAwaiting) {
            return $this->row(
                $application,
                $resolved,
                'none',
                'awaiting_guarantor',
                ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR,
                'Gate One passed. Still awaiting guarantor ('.$progress.').',
            );
        }

        if ($required) {
            return $this->row($application, $resolved, 'transition', 'awaiting_guarantor', ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR, 'Gate One passed. Guarantor nominated but not complete ('.$progress.').');
        }

        return $this->row($application, $resolved, 'transition', 'submitted', ApplicationIntakeReadinessService::STATE_READY, 'Gate One passed. No outstanding guarantor requirement. Ready for screening.');
    }

    private function alreadyParked(LoanApplication $application): bool
    {
        if ($this->capacity->isPending($application)) {
            return true;
        }

        return (string) $application->current_stage === ApplicationIntakeReadinessService::STATE_HOLD
            && data_get($application->screening_payload, 'intake.initial_gate.result') === 'failed';
    }

    /**
     * @param  array<string, mixed>  $resolved
     * @return array<string, mixed>
     */
    private function row(
        LoanApplication $application,
        array $resolved,
        string $action,
        string $toStatus,
        string $toStage,
        string $reason,
    ): array {
        return [
            'application_number' => $application->application_number,
            'borrower' => $application->customer?->full_name,
            'amount' => $application->requested_amount,
            'action' => $action,
            'from_status' => $application->status,
            'from_stage' => $application->current_stage,
            'to_status' => $toStatus,
            'to_stage' => $toStage,
            'reason' => $reason,
            'state' => $resolved['state'] ?? null,
            'draft_reason' => $resolved['draft_reason'] ?? null,
            'profile_percent' => $resolved['profile_percent'] ?? null,
            'profile_gaps' => $resolved['profile_gaps'] ?? [],
            'guarantor_progress' => $resolved['guarantor']['progress'] ?? null,
            'gate_one_action' => $resolved['gate_one_action'] ?? null,
            'gate_one_reason' => $resolved['gate_one_reason'] ?? null,
            'notified' => false,
            'applied' => false,
        ];
    }

    /** @param  array<string, mixed>  $proposal */
    private function applyProposal(LoanApplication $application, array $proposal, bool $notify): void
    {
        if (($proposal['action'] ?? '') === 'clear_closed_stage') {
            $application->update(['current_stage' => (string) $application->status]);

            return;
        }

        if (($proposal['action'] ?? '') === 'park') {
            $this->transitions->parkFailedFirstGate($application->fresh(['customer', 'product']), notify: $notify);

            return;
        }

        if (($proposal['action'] ?? '') === 'return_to_incomplete') {
            $application->update([
                'status' => 'draft',
                'current_stage' => 'draft',
            ]);
            $this->drafts->ensureResumeDraftForApplication($application->fresh(['customer', 'product']));

            return;
        }

        if (($proposal['action'] ?? '') !== 'transition') {
            return;
        }

        $application->update([
            'status' => $proposal['to_status'],
            'current_stage' => $proposal['to_stage'],
        ]);

        if ($proposal['to_stage'] === ApplicationIntakeReadinessService::STATE_READY) {
            $application->update(['guarantor_deadline_at' => null]);
        }

        if ($notify && $proposal['to_stage'] === ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR) {
            $this->transitions->dispatchGuarantorInvitation($application->fresh(['customer', 'product']));
        }
    }
}

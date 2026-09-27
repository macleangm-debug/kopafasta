<?php

namespace App\Services;

use App\Models\LoanApplication;
use Illuminate\Support\Collection;

class ApplicationIntakeReconciliationService
{
    public function __construct(
        private readonly ApplicationIntakeReadinessService $readiness,
        private readonly ApplicationIntakeTransitionService $transitions,
        private readonly AuditService $audit,
    ) {}

    /**
     * Identify submitted applications stranded as Draft, leftover awaiting-guarantor
     * stages on closed records, and Steward-like complete files.
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
            $proposal['notified'] = $notify;
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
                    $q->whereIn('status', ApplicationIntakeReadinessService::CLOSED_STATUSES)
                        ->where('current_stage', 'awaiting_guarantor');
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

        if ($closed && $fromStage === 'awaiting_guarantor') {
            return $this->row($application, $resolved, 'clear_closed_stage', $fromStatus, $fromStatus, 'Closed application must not keep an awaiting-guarantor stage.');
        }

        if ($fromStatus !== 'draft' || blank($application->submitted_at)) {
            return $this->row($application, $resolved, 'none', $fromStatus, $fromStatus, 'No intake correction required.');
        }

        $borrowerComplete = (bool) ($resolved['borrower_complete'] ?? false);
        $guarantor = $resolved['guarantor'] ?? [];
        $progress = (string) ($guarantor['progress'] ?? 'not_nominated');
        $required = (bool) ($resolved['guarantor_required'] ?? false);

        if (! $borrowerComplete) {
            return $this->row($application, $resolved, 'none', 'draft', 'draft', 'Borrower still has named profile gaps; remain Draft.');
        }

        if ($required && $progress === 'not_nominated') {
            return $this->row($application, $resolved, 'transition', 'submitted', ApplicationIntakeReadinessService::STATE_GUARANTOR_MISSING, 'Submitted borrower file without a nominated guarantor.');
        }

        if ($required && $progress === 'completed') {
            return $this->row($application, $resolved, 'transition', 'submitted', ApplicationIntakeReadinessService::STATE_READY, 'Borrower and guarantor complete. Ready for screening.');
        }

        if ($required) {
            return $this->row($application, $resolved, 'transition', 'awaiting_guarantor', ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR, 'Borrower submitted and nominated a guarantor. Waiting for guarantor.');
        }

        return $this->row($application, $resolved, 'transition', 'submitted', ApplicationIntakeReadinessService::STATE_READY, 'Borrower submitted a complete file. Ready for screening.');
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

        if (($proposal['action'] ?? '') !== 'transition') {
            return;
        }

        $application->update([
            'status' => $proposal['to_status'],
            'current_stage' => $proposal['to_stage'],
        ]);

        if ($notify && $proposal['to_stage'] === ApplicationIntakeReadinessService::STATE_AWAITING_GUARANTOR) {
            $this->transitions->dispatchGuarantorInvitation($application->fresh(['customer', 'product']));
        }
    }
}

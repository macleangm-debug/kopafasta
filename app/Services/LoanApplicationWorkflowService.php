<?php

namespace App\Services;

use App\Models\ApplicationStageHistory;
use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LoanApplicationWorkflowService
{
    /** @var array<string, array{label: string, to_stage: string, permission: string, icon?: string}> */
    public const ACTIONS = [
        'acknowledge' => [
            'label'      => 'Acknowledge receipt',
            'to_stage'   => 'screening',
            'permission' => 'applications.acknowledge',
            'from'       => ['submitted'],
        ],
        'complete_screening' => [
            'label'      => 'Complete screening → review',
            'to_stage'   => 'credit_appraisal',
            'permission' => 'applications.review',
            'from'       => ['screening'],
        ],
        'pre_approve' => [
            'label'      => 'Send to pre-approval',
            'to_stage'   => 'pre_approval',
            'permission' => 'applications.pre_approve',
            'from'       => ['credit_appraisal'],
        ],
        'approve' => [
            'label'      => 'Final approve',
            'to_stage'   => 'approval',
            'permission' => 'applications.approve',
            'from'       => ['pre_approval'],
        ],
        'disburse' => [
            'label'      => 'Mark ready for disbursement',
            'to_stage'   => 'disbursement',
            'permission' => 'applications.disburse',
            'from'       => ['approval'],
        ],
        'reject' => [
            'label'      => 'Reject application',
            'to_stage'   => 'rejected',
            'permission' => 'applications.reject',
            'from'       => ['submitted', 'screening', 'credit_appraisal', 'pre_approval', 'approval'],
        ],
    ];

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly AuditService $audit,
    ) {}

    /** @return Collection<int, array{key: string, label: string, to_stage: string, permission: string}> */
    public function availableActions(LoanApplication $application, User $user): Collection
    {
        $stage = $application->current_stage ?? 'submitted';

        return collect(self::ACTIONS)
            ->filter(fn (array $action) => in_array($stage, $action['from'], true))
            ->filter(fn (array $action, string $key) => $this->permissions->has($user, $action['permission']))
            ->filter(fn (array $action) => $this->sameBranch($user, $application))
            ->map(fn (array $action, string $key) => [
                'key'        => $key,
                'label'      => $action['label'],
                'to_stage'   => $action['to_stage'],
                'permission' => $action['permission'],
            ])
            ->values();
    }

    public function transition(
        LoanApplication $application,
        User $user,
        string $actionKey,
        ?string $remarks = null,
        bool $overrideAffordability = false,
    ): LoanApplication {
        $action = self::ACTIONS[$actionKey] ?? null;

        if (! $action) {
            throw ValidationException::withMessages(['action' => 'Invalid workflow action.']);
        }

        if (! $this->permissions->has($user, $action['permission'])) {
            throw ValidationException::withMessages(['action' => 'You do not have permission for this action.']);
        }

        if (! $this->sameBranch($user, $application)) {
            throw ValidationException::withMessages(['action' => 'This application belongs to another branch.']);
        }

        $from = $application->current_stage ?? 'submitted';

        if (! in_array($from, $action['from'], true)) {
            throw ValidationException::withMessages(['action' => 'This action is not available at the current stage.']);
        }

        $to = $action['to_stage'];

        $appraisal = $application->credit_appraisal_payload ?? [];

        if (in_array($to, ['credit_appraisal', 'pre_approval', 'approval', 'disbursement'], true)) {
            $result = app(AffordabilityService::class)->evaluate($application->loadMissing(['customer', 'product']));
            $appraisal['affordability'] = $result;

            if ($result['verdict'] === 'fail' && ! $overrideAffordability) {
                throw ValidationException::withMessages([
                    'affordability' => 'Affordability check failed: '.($result['reason'] ?? 'DSR too high'),
                ]);
            }
        }

        if (in_array($to, ['pre_approval', 'approval', 'disbursement'], true) && ! in_array($user->role, ['admin', 'super_admin'], true)) {
            $limit = (float) ($user->approval_limit ?? 0);
            $amount = (float) ($application->recommended_amount ?? $application->requested_amount);

            if ($amount > $limit) {
                throw ValidationException::withMessages([
                    'approval_limit' => 'Amount exceeds your approval limit of '.format_money($limit).'.',
                ]);
            }
        }

        $oldStatus = $application->status;

        $application->update([
            'current_stage'             => $to,
            'status'                    => $this->statusForStage($to, $oldStatus),
            'pre_approved_at'           => $to === 'pre_approval' ? now() : $application->pre_approved_at,
            'approved_at'               => $to === 'approval' ? now() : $application->approved_at,
            'rejection_reason'          => $to === 'rejected' ? ($remarks ?: $application->rejection_reason) : $application->rejection_reason,
            'credit_appraisal_payload'  => $appraisal,
        ]);

        if ($to === 'approval') {
            app(PostApprovalFeeService::class)->generateForApplication($application->fresh(['product']));
            app(AssetReservationService::class)->syncFromApplication($application->fresh());
            app(LoanOriginationService::class)->createFromApplication($application->fresh(['customer', 'product', 'loan']));
        }

        if ($to === 'disbursement') {
            app(LoanOriginationService::class)->createFromApplication($application->fresh(['customer', 'product', 'loan']));
        }

        ApplicationStageHistory::create([
            'loan_application_id' => $application->id,
            'from_stage'          => $from,
            'to_stage'            => $to,
            'changed_by'          => $user->id,
            'remarks'             => $remarks,
        ]);

        $this->audit->log($user, 'application.stage_changed', $application, [
            'current_stage' => $from,
            'status'        => $oldStatus,
        ], [
            'current_stage' => $to,
            'status'        => $application->status,
            'action'        => $actionKey,
            'remarks'       => $remarks,
        ]);

        return $application->fresh(['stageHistory.changedByUser', 'customer', 'product']);
    }

    /** Direct stage transition (API / advanced). Requires applications.approve or stage-specific permission. */
    public function transitionToStage(
        LoanApplication $application,
        User $user,
        string $toStage,
        ?string $remarks = null,
        bool $overrideAffordability = false,
    ): LoanApplication {
        if (! in_array($toStage, LoanApplication::STAGES, true)) {
            throw ValidationException::withMessages(['to_stage' => 'Invalid stage.']);
        }

        $permission = match ($toStage) {
            'screening'        => 'applications.acknowledge',
            'credit_appraisal' => 'applications.review',
            'pre_approval'     => 'applications.pre_approve',
            'approval'         => 'applications.approve',
            'disbursement'     => 'applications.disburse',
            'rejected'         => 'applications.reject',
            default            => 'applications.view',
        };

        if (! $this->permissions->has($user, $permission)) {
            throw ValidationException::withMessages(['to_stage' => 'You do not have permission to move to this stage.']);
        }

        if (! $this->sameBranch($user, $application)) {
            throw ValidationException::withMessages(['to_stage' => 'Branch mismatch.']);
        }

        $from = $application->current_stage ?? 'submitted';
        $appraisal = $application->credit_appraisal_payload ?? [];

        if (in_array($toStage, ['credit_appraisal', 'pre_approval', 'approval', 'disbursement'], true)) {
            $result = app(AffordabilityService::class)->evaluate($application->loadMissing(['customer', 'product']));
            $appraisal['affordability'] = $result;

            if ($result['verdict'] === 'fail' && ! $overrideAffordability) {
                throw ValidationException::withMessages([
                    'affordability' => 'Affordability check failed: '.($result['reason'] ?? 'DSR too high'),
                ]);
            }
        }

        if (in_array($toStage, ['pre_approval', 'approval', 'disbursement'], true) && ! in_array($user->role, ['admin', 'super_admin'], true)) {
            $limit = (float) ($user->approval_limit ?? 0);
            $amount = (float) ($application->recommended_amount ?? $application->requested_amount);

            if ($amount > $limit) {
                throw ValidationException::withMessages(['approval_limit' => 'Approval limit exceeded.']);
            }
        }

        $oldStatus = $application->status;

        $application->update([
            'current_stage'            => $toStage,
            'status'                   => $this->statusForStage($toStage, $oldStatus),
            'pre_approved_at'          => $toStage === 'pre_approval' ? now() : $application->pre_approved_at,
            'approved_at'              => $toStage === 'approval' ? now() : $application->approved_at,
            'rejection_reason'         => $toStage === 'rejected' ? ($remarks ?: $application->rejection_reason) : $application->rejection_reason,
            'credit_appraisal_payload' => $appraisal,
        ]);

        if ($toStage === 'approval') {
            app(PostApprovalFeeService::class)->generateForApplication($application->fresh(['product']));
            app(AssetReservationService::class)->syncFromApplication($application->fresh());
            app(LoanOriginationService::class)->createFromApplication($application->fresh(['customer', 'product', 'loan']));
        }

        if ($toStage === 'disbursement') {
            app(LoanOriginationService::class)->createFromApplication($application->fresh(['customer', 'product', 'loan']));
        }

        ApplicationStageHistory::create([
            'loan_application_id' => $application->id,
            'from_stage'          => $from,
            'to_stage'            => $toStage,
            'changed_by'          => $user->id,
            'remarks'             => $remarks,
        ]);

        $this->audit->log($user, 'application.stage_changed', $application, [
            'current_stage' => $from,
            'status'        => $oldStatus,
        ], [
            'current_stage' => $toStage,
            'status'        => $application->status,
            'remarks'       => $remarks,
        ]);

        return $application->fresh(['stageHistory', 'customer', 'product']);
    }

    public function stageLabel(string $stage): string
    {
        return match ($stage) {
            'submitted'         => 'Submitted',
            'screening'         => 'Screening / documents',
            'credit_appraisal'  => 'Credit review',
            'pre_approval'      => 'Pre-approval',
            'approval'          => 'Final approval',
            'disbursement'      => 'Disbursement',
            'rejected'          => 'Rejected',
            default             => ucfirst(str_replace('_', ' ', $stage)),
        };
    }

    private function statusForStage(string $toStage, ?string $previous): string
    {
        return match ($toStage) {
            'rejected'     => 'rejected',
            'disbursement' => 'approved',
            'pre_approval' => 'pre_approved',
            'approval'     => 'approved',
            'credit_appraisal' => 'under_review',
            'screening'    => 'submitted',
            default        => in_array($previous, ['draft', 'awaiting_guarantor'], true) ? ($previous ?? 'in_progress') : 'in_progress',
        };
    }

    private function sameBranch(User $user, LoanApplication $application): bool
    {
        if (in_array($user->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        $recordBranch = $application->branch_id ?: $application->customer?->branch_id;

        if (! $user->branch_id || ! $recordBranch) {
            return false;
        }

        return (int) $user->branch_id === (int) $recordBranch;
    }
}

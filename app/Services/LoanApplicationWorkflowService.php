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
            'label'      => 'Complete screening',
            'to_stage'   => 'credit_appraisal',
            'permission' => 'applications.review',
            'from'       => ['screening'],
        ],
        'submit_recommendation' => [
            'label'      => 'Submit credit recommendation',
            'to_stage'   => 'pre_approval',
            'permission' => 'applications.review',
            'from'       => ['credit_appraisal'],
        ],
        'suggest_asset_alternative' => [
            'label'      => 'Suggest asset-backed alternative',
            'to_stage'   => 'credit_appraisal',
            'permission' => 'applications.review',
            'from'       => ['credit_appraisal'],
        ],
        'issue_offer' => [
            'label'      => 'Issue offer to borrower',
            'to_stage'   => 'pre_approval',
            'permission' => 'applications.pre_approve',
            'from'       => ['pre_approval'],
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
        'return_for_documents' => [
            'label'      => 'Return for documents',
            'to_stage'   => 'screening',
            'permission' => 'applications.review',
            'from'       => ['screening', 'credit_appraisal', 'pre_approval'],
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
            ->filter(fn (array $action, string $key) => ! (
                $key === 'acknowledge'
                && $application->status === 'awaiting_guarantor'
                && app(UnderwritingSettingsService::class)->blockAcknowledgeWithoutGuarantor()
            ))
            ->filter(fn (array $action, string $key) => ! ($key === 'issue_offer' && (
                ! app(UnderwritingSettingsService::class)->counterOffersEnabled()
                || $application->recommendation_type !== ApplicationOfferService::RECOMMEND_COUNTER
                || $application->offer_status === 'pending_borrower'
            )))
            ->filter(fn (array $action, string $key) => ! ($key === 'suggest_asset_alternative' && ! app(UnderwritingSettingsService::class)->assetBackedAlternativeEnabled()))
            ->filter(fn (array $action, string $key) => ! ($key === 'approve' && ! app(ApplicationOfferService::class)->canFinalApprove($application)))
            ->filter(fn (array $action, string $key) => ! ($key === 'disburse' && ! app(ApplicationDisbursementReadinessService::class)->canMarkDisbursement($application)))
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
        ?string $rejectionReasonCode = null,
        ?string $rejectionInternalNotes = null,
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

        if ($actionKey === 'acknowledge'
            && $application->status === 'awaiting_guarantor'
            && app(UnderwritingSettingsService::class)->blockAcknowledgeWithoutGuarantor()) {
            throw ValidationException::withMessages(['action' => 'Underwriting cannot start until the guarantor accepts and completes their profile.']);
        }

        if ($actionKey === 'return_for_documents' && blank(trim((string) $remarks))) {
            throw ValidationException::withMessages(['remarks' => 'Explain which documents the borrower must provide or update.']);
        }

        if ($actionKey === 'complete_screening') {
            $dossier = app(LoanApplicationReviewService::class)->dossier($application);
            if (($dossier['document_progress'] ?? 0) < 100) {
                throw ValidationException::withMessages([
                    'action' => 'All required documents must be uploaded and verified before completing screening.',
                ]);
            }
        }

        if ($actionKey === 'disburse') {
            $blocking = app(ApplicationDisbursementReadinessService::class)->blockingMessages($application);
            if ($blocking !== []) {
                throw ValidationException::withMessages([
                    'action' => implode(' ', $blocking),
                ]);
            }
        }

        if ($actionKey === 'approve' && ! app(ApplicationOfferService::class)->canFinalApprove($application)) {
            throw ValidationException::withMessages([
                'action' => 'Counter-offers must be accepted by the borrower before final approval.',
            ]);
        }

        $to = $action['to_stage'];

        $appraisal = $application->credit_appraisal_payload ?? [];

        if (in_array($to, ['credit_appraisal', 'pre_approval', 'approval', 'disbursement'], true)) {
            $result = app(AffordabilityService::class)->evaluate($application->loadMissing(['customer', 'product']));
            $appraisal['affordability'] = $result;

            if ($result['verdict'] === 'fail' && ! $overrideAffordability
                && in_array($to, ['pre_approval', 'approval', 'disbursement'], true)
                && ! ($actionKey === 'submit_recommendation' && $application->recommendation_type === ApplicationOfferService::RECOMMEND_COUNTER)) {
                throw ValidationException::withMessages([
                    'affordability' => 'Affordability check failed: '.($result['reason'] ?? 'DSR too high'),
                ]);
            }
        }

        if (in_array($to, ['pre_approval', 'approval', 'disbursement'], true) && ! in_array($user->role, ['admin', 'super_admin'], true)) {
            $limit = (float) ($user->approval_limit ?? 0);
            $amount = app(ApplicationOfferService::class)->effectiveAmount($application);

            if ($amount > $limit) {
                throw ValidationException::withMessages([
                    'approval_limit' => 'Amount exceeds your approval limit of '.format_money($limit).'.',
                ]);
            }
        }

        $oldStatus = $application->status;
        $rejectionLabel = null;

        if ($to === 'rejected') {
            $reasonService = app(LoanRejectionReasonService::class);
            if (! $reasonService->isValidCode($rejectionReasonCode)) {
                throw ValidationException::withMessages([
                    'rejection_reason_code' => 'Select a valid rejection reason.',
                ]);
            }
            $rejectionLabel = $reasonService->labelForCode($rejectionReasonCode);
        }

        $application->update([
            'current_stage'             => $to,
            'status'                    => $actionKey === 'return_for_documents'
                ? 'pending_documents'
                : $this->statusForStage($to, $oldStatus),
            'pre_approved_at'           => $to === 'pre_approval' ? now() : $application->pre_approved_at,
            'approved_at'               => $to === 'approval' ? now() : $application->approved_at,
            'rejection_reason_code'     => $to === 'rejected' ? $rejectionReasonCode : $application->rejection_reason_code,
            'rejection_reason'          => $to === 'rejected' ? ($rejectionLabel ?: $application->rejection_reason) : $application->rejection_reason,
            'rejection_internal_notes'  => $to === 'rejected' ? $rejectionInternalNotes : $application->rejection_internal_notes,
            'credit_appraisal_payload'  => $appraisal,
        ]);

        if ($actionKey === 'return_for_documents') {
            $this->notifyReturnForDocuments($application->fresh(['customer']), $remarks);
        }

        if ($to === 'rejected') {
            $this->notifyRejection($application->fresh(['customer']));
            $loan = $application->fresh(['loan'])->loan;
            if ($loan && $loan->status === 'pending') {
                app(CapitalPartnerAllocationService::class)->releaseAllocationForLoan($loan);
            }
        }

        if ($to === 'approval') {
            app(PostApprovalFeeService::class)->generateForApplication($application->fresh(['product']));
            app(AssetReservationService::class)->syncFromApplication($application->fresh());
            app(LoanOriginationService::class)->createFromApplication($application->fresh(['customer', 'product', 'loan']));
            app(GuarantorNotificationService::class)->notifyLoanApproved($application->fresh(['customer', 'product']));
            $this->issueOfferLetterOnApproval($application->fresh(['customer', 'product']));
        }

        if ($to === 'disbursement') {
            app(LoanOriginationService::class)->createFromApplication($application->fresh(['customer', 'product', 'loan']));
        }

        ApplicationStageHistory::create([
            'loan_application_id' => $application->id,
            'from_stage'          => $from,
            'to_stage'            => $to,
            'changed_by'          => $user->id,
            'remarks'             => $to === 'rejected'
                ? ($rejectionInternalNotes ?: $remarks)
                : $remarks,
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

        if ($application->loan_group_id) {
            app(GroupApplicationStatusService::class)->syncApplication($application->fresh(['loanGroup', 'loan']));
        }

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

            if ($result['verdict'] === 'fail' && ! $overrideAffordability
                && in_array($toStage, ['pre_approval', 'approval', 'disbursement'], true)) {
                throw ValidationException::withMessages([
                    'affordability' => 'Affordability check failed: '.($result['reason'] ?? 'DSR too high'),
                ]);
            }
        }

        if (in_array($toStage, ['pre_approval', 'approval', 'disbursement'], true) && ! in_array($user->role, ['admin', 'super_admin'], true)) {
            $limit = (float) ($user->approval_limit ?? 0);
            $amount = app(ApplicationOfferService::class)->effectiveAmount($application);

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
            $this->issueOfferLetterOnApproval($application->fresh(['customer', 'product']));
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
            'submitted'           => 'Submitted',
            'screening'           => 'Screening',
            'credit_appraisal'    => 'Credit review',
            'pre_approval'        => 'Pre-approval',
            'approval'            => 'Final approval',
            'disbursement'        => 'Disbursement',
            'awaiting_guarantor'  => 'Awaiting guarantor',
            'awaiting_disbursement_details' => 'Awaiting disbursement details',
            'post_approval_fees'  => 'Post-approval fees',
            'contract_generation' => 'Contract generation',
            'rejected'            => 'Rejected',
            default               => ucfirst(str_replace('_', ' ', $stage)),
        };
    }

    private function issueOfferLetterOnApproval(LoanApplication $application): void
    {
        app(LoanAgreementService::class)->generateOfferLetter($application);

        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        app(NotificationService::class)->notifyInApp(
            $customer,
            __('borrower.offer_letter.notify_message', [
                'reference' => $application->application_number,
            ]),
            'application',
            'offer_letter_ready',
            __('borrower.offer_letter.notify_title'),
            route('site.borrower.application.agreement', $application->id),
            __('borrower.application.review_sign'),
        );
    }

    private function notifyReturnForDocuments(LoanApplication $application, ?string $remarks): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $message = trim((string) $remarks) ?: 'Additional documents are required for your application.';

        app(NotificationService::class)->notifyInApp(
            $customer,
            __('borrower.loan_profile.underwriter_feedback', ['items' => $message]),
            'application',
            'application_document_request',
            __('borrower.dashboard.document_requests_title'),
            route('site.borrower.application', $application->id),
            __('borrower.dashboard.document_requests_cta'),
        );
    }

    private function notifyRejection(LoanApplication $application): void
    {
        $customer = $application->customer;
        if (! $customer) {
            return;
        }

        $reason = app(LoanRejectionReasonService::class)->labelForCode($application->rejection_reason_code)
            ?: $application->rejection_reason
            ?: 'Application declined';

        $name = $customer->full_name ?? $customer->first_name ?? 'Customer';

        $vars = [
            'name'               => $name,
            'application_number' => $application->application_number,
            'reason'             => $reason,
            '_fallback_body'     => 'Hi '.$name.', your loan application '.$application->application_number.' was not approved. Reason: '.$reason.'. — Kopa Fasta',
            '_fallback_subject'  => 'Loan application update',
        ];

        app(NotificationService::class)->notifyCustomer($customer, 'application_rejected', $vars);
        app(NotificationService::class)->notifyInApp(
            $customer,
            __('borrower.notifications.application_rejected', [
                'reference' => $application->application_number,
                'reason'    => $reason,
            ]),
            'application',
            'application_rejected',
            __('borrower.notifications.application_rejected_title'),
            route('site.borrower.application', $application->id),
            __('borrower.notifications.view_application'),
        );
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

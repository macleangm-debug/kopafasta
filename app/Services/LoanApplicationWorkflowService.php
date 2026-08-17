<?php

namespace App\Services;

use App\Models\ApplicationStageHistory;
use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Models\LoanProductRequirement;
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
            // Kept for backwards compatibility — hidden from the desk UI.
            'label'      => 'Complete screening',
            'to_stage'   => 'credit_appraisal',
            'permission' => 'applications.review',
            'from'       => ['screening'],
        ],
        'submit_recommendation' => [
            'label'      => 'Record screening decision',
            'to_stage'   => 'pre_approval',
            'permission' => 'applications.review',
            'from'       => ['submitted', 'screening', 'credit_appraisal'],
        ],
        'validate_screening' => [
            'label'      => 'Validate screening decision',
            'to_stage'   => 'pre_approval',
            'permission' => 'applications.pre_approve',
            'from'       => ['pre_approval'],
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
        if ($application->isClosed()) {
            return collect();
        }

        $stage = $application->current_stage ?? 'submitted';

        return collect(self::ACTIONS)
            // Document gaps are requested from Review checklist → Docs, not a separate workflow CTA.
            ->filter(fn (array $action, string $key) => ! in_array($key, ['complete_screening', 'return_for_documents'], true))
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
            ->filter(fn (array $action, string $key) => ! ($key === 'validate_screening' && ! app(ApplicationOfferService::class)->canValidateScreening($application, $user)))
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
        ?string $rejectionAdviceCode = null,
        ?string $rejectionAdvice = null,
        ?array $rejectionReasonCodes = null,
        ?string $approvalReasonCode = null,
        ?string $approvalReasonNotes = null,
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

        if ($application->isClosed()) {
            throw ValidationException::withMessages([
                'action' => 'This application is closed and can only be viewed.',
            ]);
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

        if ($actionKey === 'complete_screening' || $actionKey === 'submit_recommendation') {
            $this->assertScreeningDocumentsReady($application);
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
        $normalizedRejectionCodes = [];

        if ($to === 'rejected') {
            $reasonService = app(LoanRejectionReasonService::class);
            $normalizedRejectionCodes = $reasonService->normalizeCodes($rejectionReasonCodes, $rejectionReasonCode);
            if ($normalizedRejectionCodes === []) {
                throw ValidationException::withMessages([
                    'rejection_reason_codes' => 'Select at least one rejection reason.',
                ]);
            }
            $rejectionReasonCode = $normalizedRejectionCodes[0];
            $rejectionLabel = $reasonService->formatReasonsForBorrower(
                $normalizedRejectionCodes,
                $rejectionReasonCode,
            );
            // Keep predefined advice as code; free-text advice is always stored when provided.
            $storedAdvice = trim((string) $rejectionAdvice) ?: null;
            if ($rejectionAdviceCode && $rejectionAdviceCode !== 'custom' && $storedAdvice === null) {
                // Preset-only advice — store code, leave free-text empty for locale resolution.
                $storedAdvice = null;
            } elseif ($storedAdvice !== null && ! filled($rejectionAdviceCode)) {
                $rejectionAdviceCode = 'custom';
            }
            $fromStage = $from;
        } else {
            $storedAdvice = null;
            $fromStage = $from;
        }

        if ($actionKey === 'approve') {
            $approvalReasons = config('credit_recommendation.approval_reasons', []);
            $approvalReasonCode = filled($approvalReasonCode) && array_key_exists($approvalReasonCode, $approvalReasons)
                ? $approvalReasonCode
                : null;
            $approvalReasonNotes = trim((string) $approvalReasonNotes) ?: null;
            if ($approvalReasonCode === 'custom' && $approvalReasonNotes === null) {
                throw ValidationException::withMessages([
                    'approval_reason_notes' => 'Enter the custom approval reason.',
                ]);
            }
            if ($approvalReasonCode === null && $approvalReasonNotes === null) {
                throw ValidationException::withMessages([
                    'approval_reason_code' => 'Select a reason for approval.',
                ]);
            }
            $appraisal['committee_approval'] = [
                'reason_code' => $approvalReasonCode,
                'reason_label' => $approvalReasonCode
                    ? ($approvalReasons[$approvalReasonCode] ?? $approvalReasonCode)
                    : null,
                'notes' => $approvalReasonNotes,
                'approved_by' => $user->id,
                'approved_at' => now()->toIso8601String(),
            ];
        }

        $application->update([
            'current_stage'             => $to,
            'status'                    => $actionKey === 'return_for_documents'
                ? 'pending_documents'
                : $this->statusForStage($to, $oldStatus),
            'pre_approved_at'           => $to === 'pre_approval' ? now() : $application->pre_approved_at,
            'approved_at'               => $to === 'approval' ? now() : $application->approved_at,
            'rejection_reason_code'     => $to === 'rejected' ? $rejectionReasonCode : $application->rejection_reason_code,
            'rejection_reason_codes'    => $to === 'rejected' ? $normalizedRejectionCodes : $application->rejection_reason_codes,
            'rejection_reason'          => $to === 'rejected' ? ($rejectionLabel ?: $application->rejection_reason) : $application->rejection_reason,
            'rejection_internal_notes'  => $to === 'rejected' ? $rejectionInternalNotes : $application->rejection_internal_notes,
            'rejection_advice_code'     => $to === 'rejected' ? ($rejectionAdviceCode ?: null) : $application->rejection_advice_code,
            'rejection_advice'          => $to === 'rejected' ? $storedAdvice : $application->rejection_advice,
            'screening_rejection_reason_code' => $to === 'rejected' && in_array($fromStage, ['submitted', 'screening', 'credit_appraisal'], true)
                ? $rejectionReasonCode
                : $application->screening_rejection_reason_code,
            'credit_appraisal_payload'  => $appraisal,
        ]);

        if ($actionKey === 'return_for_documents') {
            $this->notifyReturnForDocuments($application->fresh(['customer']), $remarks);
        }

        if ($to === 'rejected') {
            $fresh = $application->fresh(['customer', 'product']);
            app(PartnerTaskLifecycleService::class)->closeForApplication(
                $fresh,
                'Closed because the application was rejected.',
            );
            try {
                app(LoanAgreementService::class)->generateRejectionLetter($fresh);
            } catch (\Throwable $e) {
                report($e);
            }
            $this->notifyRejection($fresh);
            $loan = $fresh->loan;
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

    /**
     * Product checklist gaps and open follow-up requests that must be in before committee.
     *
     * @return list<string>
     */
    public function screeningDocumentBlockers(LoanApplication $application): array
    {
        $dossier = app(LoanApplicationReviewService::class)->dossier($application);
        $blockers = [];

        foreach ((array) ($dossier['missing_documents'] ?? []) as $name) {
            $label = trim((string) $name);
            if ($label === '') {
                continue;
            }
            if (LoanProductRequirement::nameIsDigitalGroupRoster($label)
                || LoanProductRequirement::nameIsGroupConstitution($label)) {
                continue;
            }
            $blockers[] = $label;
        }

        $application->loadMissing(['documentRequests.subjectCustomer', 'documentRequests.groupMember.customer']);
        foreach ($application->documentRequests as $request) {
            if (! $request->needsBorrowerAction()) {
                continue;
            }
            $label = trim((string) ($request->label ?? 'Requested document'));
            $who = $request->subjectRoleLabel();
            $blockers[] = $who ? $label.' ('.$who.')' : $label;
        }

        return array_values(array_unique($blockers));
    }

    public function assertScreeningDocumentsReady(LoanApplication $application): void
    {
        $blockers = $this->screeningDocumentBlockers($application);
        if ($blockers === []) {
            return;
        }

        $lines = array_merge(
            ['Cannot push to committee until every requested document is submitted and verified.'],
            $blockers,
        );

        throw ValidationException::withMessages(['action' => $lines]);
    }

    public function stageLabel(string $stage): string
    {
        return match ($stage) {
            'submitted'           => 'Submitted',
            'screening'           => 'Screening',
            'credit_appraisal'    => 'Credit appraisal',
            'pre_approval'        => 'Pre-approval',
            'approval'            => 'Committee approval',
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

        $locale = optional($customer->user)->locale
            ?? data_get(optional($customer->user)->preferences, 'preferred_locale')
            ?? data_get(optional($customer->user)->preferences, 'locale')
            ?? app()->getLocale();

        if (! in_array($locale, ['en', 'sw'], true)) {
            $locale = 'en';
        }

        $reasonService = app(LoanRejectionReasonService::class);
        $reason = $reasonService->formatReasonsForBorrower(
            $application->rejection_reason_codes,
            $application->rejection_reason_code,
            $application->rejection_reason,
            $locale,
        );

        $advice = $reasonService->resolveBorrowerAdvice(
            $application->rejection_advice_code,
            $application->rejection_advice,
            $locale,
        );

        $name = $customer->full_name ?? $customer->first_name ?? 'Customer';

        $vars = [
            'name'               => $name,
            'application_number' => $application->application_number,
            'reason'             => $reason,
            'advice'             => $advice ? ' Advice: '.$advice : '',
            '_fallback_body'     => 'Hi '.$name.', your loan application '.$application->application_number.' was not approved. Reason: '.$reason.'.'
                .($advice ? ' Advice: '.$advice : '')
                .' — Kopa Fasta',
            '_fallback_subject'  => 'Loan application update',
        ];

        $letterUrl = route('site.borrower.application', $application->id);
        $rejectionLetter = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'rejection_letter')
            ->latest('id')
            ->first();
        if ($rejectionLetter) {
            $letterUrl = route('site.borrower.application.rejection-letter', $application->id);
        }

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
            $letterUrl,
            $rejectionLetter
                ? __('borrower.rejection_letter.notify_cta')
                : __('borrower.notifications.view_application'),
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

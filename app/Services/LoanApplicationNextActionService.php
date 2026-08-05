<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;

class LoanApplicationNextActionService
{
    public function __construct(
        private readonly ApplicationProgressService $progress,
        private readonly ApplicationRequirementsService $requirements,
        private readonly LoanApplicationDraftService $drafts,
    ) {}

    /**
     * @return array{
     *     code: string,
     *     label: string,
     *     button_label: string,
     *     url: string,
     *     tone: string,
     *     can_submit: bool,
     *     ready: bool
     * }
     */
    public function forDraft(Customer $customer, LoanApplicationDraft $draft, ?LoanProduct $product): array
    {
        $profileUrl = route('site.borrower.loan-profile.draft', $draft);
        $resumeTarget = $this->drafts->resumeTarget($customer, $draft);
        $wizardUrl = $this->drafts->wizardApplyUrl($draft, $resumeTarget);
        $requirements = collect($this->progress->profileRequirements($customer, $product));
        $firstMissing = $requirements->first(fn (array $item) => ! ($item['complete'] ?? false) && filled($item['action_url'] ?? null));

        if ($firstMissing) {
            // Always resume the wizard — profile gaps are surfaced inside the apply flow.
            return $this->action(
                'continue_application',
                __('borrower.loan_profile.next_actions.continue_form'),
                __('borrower.loan_profile.actions.continue_to_form'),
                $wizardUrl,
            );
        }

        if ($product && quoted_application_fee($customer, $product) > 0) {
            $fee = ($draft->payload ?? [])['application_fee'] ?? null;
            if (! app(ApplicationFeePaymentService::class)->isFeeSatisfied($fee, quoted_application_fee($customer, $product))) {
                return $this->action(
                    'pay_application_fee',
                    __('borrower.loan_profile.next_actions.application_fee'),
                    __('borrower.loan_profile.actions.continue_to_form'),
                    $this->wizardUrlWithStep($wizardUrl, 'application_fee'),
                );
            }
        }

        $payload = $draft->payload ?? [];
        $applicationStarted = (bool) ($payload['application_started'] ?? $draft->phase === 'application');
        $signature = $payload['borrower_signature']
            ?? app(BorrowerSignatureService::class)->profileSignature($customer);
        $hasSignature = filled($signature['signature_data'] ?? null);
        $stepKey = (string) ($resumeTarget['step_key'] ?? $payload['step_key'] ?? '');

        if (! $applicationStarted || ($resumeTarget['phase'] ?? '') !== 'application') {
            return $this->action(
                'continue_application',
                __('borrower.loan_profile.next_actions.continue_form'),
                __('borrower.loan_profile.actions.continue_to_form'),
                $wizardUrl,
            );
        }

        if ($hasSignature) {
            return $this->action(
                'submit_application',
                __('borrower.loan_profile.next_actions.submit'),
                __('borrower.loan_profile.actions.submit_application'),
                $this->wizardUrlWithStep($wizardUrl, 'submit'),
                tone: 'primary',
                canSubmit: (bool) ($this->requirements->checklist($customer)['can_apply'] ?? false),
                ready: true,
            );
        }

        if ($stepKey === 'signature' || $stepKey === 'submit') {
            $signatureUrl = app(BorrowerSignatureService::class)->hasProfileSignature($customer)
                ? $this->wizardUrlWithStep($wizardUrl, 'submit')
                : route('site.borrower.profile', ['section' => 'personal', 'focus' => 'signature']).'?return='.urlencode($profileUrl);

            return $this->action(
                'sign_application',
                __('borrower.loan_profile.next_actions.sign'),
                __('borrower.loan_profile.actions.sign_application'),
                $signatureUrl,
                tone: 'primary',
                canSubmit: (bool) ($this->requirements->checklist($customer)['can_apply'] ?? false),
                ready: true,
            );
        }

        if ($stepKey === 'review') {
            return $this->action(
                'review_application',
                __('borrower.loan_profile.next_actions.review'),
                __('borrower.loan_profile.actions.review_application'),
                $this->wizardUrlWithStep($wizardUrl, 'review'),
                tone: 'primary',
                ready: true,
            );
        }

        return $this->action(
            'continue_application',
            __('borrower.loan_profile.next_actions.continue_form'),
            __('borrower.loan_profile.actions.continue_to_form'),
            $this->wizardUrlWithStep($wizardUrl, $stepKey ?: null),
        );
    }

    /** @return array{code: string, label: string, button_label: string, url: string, tone: string, can_submit: bool, ready: bool} */
    public function forApplication(Customer $customer, LoanApplication $application, array $missingRequirements = []): array
    {
        $status = (string) $application->status;
        $profileUrl = route('site.borrower.application', $application->id);

        if ($status === 'disbursed') {
            $loan = \App\Models\Loan::query()->where('loan_application_id', $application->id)->first();

            return $this->action(
                'view_loan',
                __('borrower.loan_profile.next_actions.disbursed'),
                __('borrower.loan_profile.actions.view_active_loan'),
                $loan ? route('site.borrower.loans.show', $loan->id) : $profileUrl,
            );
        }

        $loan = \App\Models\Loan::query()->where('loan_application_id', $application->id)->first();
        if ($loan && in_array((string) $loan->status, ['active', 'disbursed', 'arrears'], true)) {
            return $this->action(
                'view_loan',
                __('borrower.loan_profile.next_actions.disbursed'),
                __('borrower.loan_profile.actions.view_active_loan'),
                route('site.borrower.loans.show', $loan->id),
            );
        }

        if ($status === 'rejected') {
            return $this->action(
                'view_rejection_reason',
                __('borrower.loan_profile.next_actions.rejected'),
                __('borrower.loan_profile.actions.view_reason'),
                $profileUrl.'#rejection',
                tone: 'secondary',
            );
        }

        if ($status === 'withdrawn' && $application->offer_status === 'declined') {
            return $this->action(
                'offer_declined',
                __('borrower.loan_profile.next_actions.offer_declined'),
                __('borrower.applications_list.view'),
                $profileUrl,
                tone: 'secondary',
            );
        }

        if ($application->offer_status === 'declined'
            || app(\App\Services\ApplicationOfferService::class)->offerDeclinedByBorrower($application)) {
            return $this->action(
                'offer_declined',
                __('borrower.loan_profile.next_actions.offer_declined'),
                __('borrower.applications_list.view'),
                $profileUrl,
                tone: 'secondary',
            );
        }

        if ($status === 'withdrawn') {
            return $this->action(
                'withdrawn',
                __('borrower.loan_profile.withdrawn_detail'),
                __('borrower.applications_list.view'),
                $profileUrl,
                tone: 'secondary',
            );
        }

        if ($status === 'awaiting_guarantor' || (string) ($application->current_stage ?? '') === 'awaiting_guarantor') {
            return $this->action(
                'awaiting_guarantor',
                __('borrower.loan_profile.next_actions.awaiting_guarantor_detail'),
                __('borrower.applications_list.view'),
                $profileUrl.'#guarantor-progress',
                tone: 'secondary',
            );
        }

        if (app(\App\Services\GuarantorSupplementService::class)->hasOpenRequest($application)) {
            return $this->action(
                'add_guarantor',
                __('borrower.loan_profile.next_actions.add_guarantor'),
                __('borrower.guarantor_supplement.cta'),
                app(\App\Services\GuarantorSupplementService::class)->borrowerWizardUrl($application),
                tone: 'primary',
            );
        }

        $collateralSecure = app(\App\Services\CollateralSecureService::class);
        if ($collateralSecure->isOpen($application)) {
            $statusCode = data_get($collateralSecure->state($application), 'status');
            $label = match ($statusCode) {
                \App\Services\CollateralSecureService::STATUS_AWAITING_FEE => __('borrower.collateral_secure.fee_title'),
                \App\Services\CollateralSecureService::STATUS_AWAITING_GUARANTOR => __('borrower.collateral_secure.waiting_guarantor'),
                \App\Services\CollateralSecureService::STATUS_AWAITING_INSURANCE => __('borrower.collateral_secure.insurance_needed'),
                default => __('borrower.collateral_secure.why'),
            };
            $button = match ($statusCode) {
                \App\Services\CollateralSecureService::STATUS_AWAITING_FEE => __('borrower.collateral_secure.pay_now'),
                \App\Services\CollateralSecureService::STATUS_AWAITING_BORROWER_ADD => __('borrower.collateral_secure.add_collateral'),
                default => __('borrower.collateral_secure.cta_open'),
            };

            return $this->action(
                'collateral_secure',
                $label,
                $button,
                $profileUrl,
                tone: 'primary',
            );
        }

        $groupDashboard = app(\App\Services\GroupMemberReplacementService::class)
            ->leaderDashboard($application, $customer);
        if ($groupDashboard && ($groupDashboard['can_replace'] ?? false)) {
            return $this->action(
                'replace_group_member',
                __('borrower.loan_profile.next_actions.replace_group_member'),
                __('borrower.apply.group.replacement_add'),
                $profileUrl.'#group-contract',
                tone: 'primary',
            );
        }

        if ($status === 'awaiting_offer' || $application->offer_status === 'pending_borrower') {
            return $this->action(
                'review_offer',
                __('borrower.loan_profile.next_actions.review_offer'),
                __('borrower.offer.review_cta'),
                route('site.borrower.application.offer', $application->id),
                tone: 'primary',
            );
        }

        $offers = app(ApplicationOfferService::class);
        if ($offers->pendingAssetConversion($application) || $offers->needsConversionFee($application)) {
            return $this->action(
                'asset_conversion',
                __('borrower.offer.asset_conversion_next_action'),
                __('borrower.offer.asset_conversion_cta'),
                route('site.borrower.application.asset-conversion', $application->id),
                tone: 'primary',
            );
        }

        $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);
        $isPostApproval = (string) $application->offer_status === 'accepted'
            || in_array($status, ['approved', 'pre_approved'], true)
            || in_array((string) ($application->current_stage ?? ''), $readiness->borrowerPostApprovalStages(), true);

        if ($isPostApproval) {
            if ($readiness->needsBorrowerSignature($application)) {
                return $this->action(
                    'sign_offer',
                    __('borrower.loan_profile.next_actions.sign_offer'),
                    __('borrower.application.view_offer'),
                    route('site.borrower.application.agreement', $application->id),
                    tone: 'primary',
                );
            }

            if ($readiness->needsPostApprovalFees($application)) {
                return $this->action(
                    'pay_post_approval_fees',
                    __('borrower.loan_profile.next_actions.offer_accepted'),
                    __('borrower.loan_profile.actions.pay_post_approval_fees'),
                    route('site.borrower.application.post-approval-fees', $application->id),
                    tone: 'primary',
                );
            }

            if ($readiness->needsDisbursementDetailsConfirmation($application)) {
                return $this->action(
                    'confirm_disbursement_details',
                    __('borrower.loan_profile.next_actions.confirm_disbursement_details'),
                    __('borrower.loan_profile.actions.confirm_disbursement_details'),
                    route('site.borrower.application.disbursement-details', $application->id),
                    tone: 'primary',
                );
            }

            if ($readiness->needsContractSignature($application)) {
                return $this->action(
                    'sign_contract',
                    __('borrower.loan_profile.next_actions.sign_contract'),
                    __('borrower.loan_profile.actions.view_contract'),
                    route('site.borrower.application.contract', $application->id),
                    tone: 'primary',
                );
            }

            if ($readiness->isAssetLendingApplication($application)) {
                $loan = \App\Models\Loan::query()->where('loan_application_id', $application->id)->first();
                $disbursed = (string) $application->status === 'disbursed'
                    || in_array((string) ($loan?->status ?? ''), ['active', 'disbursed'], true);

                if ($disbursed) {
                    return $this->action(
                        'view_loan',
                        __('borrower.loan_profile.next_actions.disbursed'),
                        __('borrower.loan_profile.actions.view_active_loan'),
                        $application->loan
                            ? route('site.borrower.loans.show', $loan->id)
                            : $profileUrl,
                    );
                }

                if ($readiness->canMarkAssetHandover($application)) {
                    return $this->action(
                        'ready_for_asset_handover',
                        __('borrower.loan_profile.next_actions.ready_for_asset_handover'),
                        __('borrower.applications_list.view'),
                        $profileUrl,
                        tone: 'secondary',
                    );
                }

                return $this->action(
                    'awaiting_asset_readiness',
                    __('borrower.loan_profile.next_actions.awaiting_asset_readiness'),
                    __('borrower.applications_list.view'),
                    $profileUrl,
                    tone: 'secondary',
                );
            }

            if ($readiness->isReadyForDisbursement($application)) {
                return $this->action(
                    'ready_for_disbursement',
                    __('borrower.loan_profile.next_actions.ready_for_disbursement'),
                    __('borrower.applications_list.view'),
                    $profileUrl,
                    tone: 'secondary',
                );
            }

            $contract = $readiness->loanContract($application);
            if ($contract && $contract->file_path) {
                return $this->action(
                    'view_contract',
                    __('borrower.loan_profile.next_actions.contract_ready'),
                    __('borrower.loan_profile.actions.view_contract'),
                    route('site.borrower.agreement.download', $contract->id),
                    tone: 'secondary',
                );
            }
        }

        // Underwriting document / profile revision requests — always surface as primary CTAs
        // with deep-links (signature/face/ID → profile; docs → request anchor).
        $openUwRequests = $application->relationLoaded('documentRequests')
            ? $application->documentRequests->filter(fn ($r) => $r->needsBorrowerAction())->values()
            : $application->documentRequests()->whereIn('status', ['pending', 'rejected'])->latest()->get();

        if ($openUwRequests->isNotEmpty()) {
            $docService = app(ApplicationDocumentRequestService::class);
            $first = $openUwRequests->first();
            $guided = $docService->borrowerGuidedAction($first);
            $count = $openUwRequests->count();

            return $this->action(
                'upload_documents',
                $count === 1
                    ? __('borrower.loan_profile.next_actions.upload', ['item' => $first->label])
                    : __('borrower.loan_profile.next_actions.upload_documents', ['count' => $count]),
                $guided['cta_label'],
                $guided['url'],
                tone: 'primary',
            );
        }

        if ($missingRequirements !== []) {
            $first = $missingRequirements[0];

            // Product requirement gaps are handled in-place on the loan profile —
            // do not surface a generic "upload documents" top CTA.
            return $this->action(
                'under_review',
                __('borrower.loan_profile.next_actions.under_review', [
                    'time' => app(UnderwritingSettingsService::class)->loanReviewSlaLabel($customer),
                ]),
                __('borrower.applications_list.view'),
                $profileUrl.'#requirement-'.($first['id'] ?? ''),
                tone: 'secondary',
            );
        }

        return $this->action(
            'under_review',
            __('borrower.loan_profile.next_actions.under_review', [
                'time' => app(UnderwritingSettingsService::class)->loanReviewSlaLabel($customer),
            ]),
            __('borrower.applications_list.view'),
            $profileUrl,
            tone: 'secondary',
        );
    }

    private function wizardUrlWithStep(string $wizardUrl, ?string $stepKey): string
    {
        if (! $stepKey) {
            return $wizardUrl;
        }

        $parts = parse_url($wizardUrl);
        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['resume'] = 1;
        $query['step_key'] = $stepKey;
        unset($query['step']);

        $base = ($parts['scheme'] ?? null) && ($parts['host'] ?? null)
            ? ($parts['scheme'].'://'.$parts['host'].($parts['port'] ?? null ? ':'.$parts['port'] : ''))
            : '';
        $path = $parts['path'] ?? '/borrower/apply';

        return $base.$path.'?'.http_build_query($query);
    }

    /** @return array{code: string, label: string, button_label: string, url: string, tone: string, can_submit: bool, ready: bool} */
    private function action(
        string $code,
        string $label,
        string $buttonLabel,
        string $url,
        string $tone = 'primary',
        bool $canSubmit = false,
        bool $ready = false,
    ): array {
        return [
            'code'          => $code,
            'label'         => $label,
            'button_label'  => $buttonLabel,
            'url'           => $url,
            'tone'          => $tone,
            'can_submit'    => $canSubmit,
            'ready'         => $ready,
        ];
    }

    private function appendReturn(string $url, string $returnUrl): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'return='.urlencode($returnUrl);
    }
}

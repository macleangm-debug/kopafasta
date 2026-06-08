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
            return $this->action(
                'upload_document',
                __('borrower.loan_profile.next_actions.upload', ['item' => $firstMissing['label']]),
                __('borrower.loan_profile.upload'),
                $this->appendReturn((string) $firstMissing['action_url'], $profileUrl),
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
        $signature = $payload['borrower_signature'] ?? null;
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
            return $this->action(
                'sign_application',
                __('borrower.loan_profile.next_actions.sign'),
                __('borrower.loan_profile.actions.sign_application'),
                $this->wizardUrlWithStep($wizardUrl, 'signature'),
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
                __('borrower.loan_profile.actions.view_schedule'),
                $loan ? route('site.borrower.schedule', $loan->id) : $profileUrl,
            );
        }

        if ($status === 'rejected') {
            return $this->action(
                'view_application',
                __('borrower.loan_profile.next_actions.rejected'),
                __('borrower.applications_list.view'),
                $profileUrl,
                tone: 'secondary',
            );
        }

        if ($missingRequirements !== []) {
            $first = $missingRequirements[0];

            return $this->action(
                'upload_document',
                __('borrower.loan_profile.next_actions.upload', ['item' => $first['label'] ?? __('borrower.loan_profile.missing_requirements_title')]),
                __('borrower.loan_profile.upload'),
                $first['upload_url'] ?? ($profileUrl.'#documents'),
            );
        }

        $offer = \App\Models\LoanAgreement::query()
            ->where('loan_application_id', $application->id)
            ->where('document_type', 'offer_letter')
            ->latest('id')
            ->first();

        if ($offer && ! $offer->isSigned()) {
            return $this->action(
                'sign_offer',
                __('borrower.loan_profile.next_actions.sign_offer'),
                __('borrower.application.review_sign'),
                route('site.borrower.application.agreement', $application->id),
                tone: 'primary',
            );
        }

        return $this->action(
            'view_application',
            __('borrower.loan_profile.next_actions.submitted'),
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

        $separator = str_contains($wizardUrl, '?') ? '&' : '?';

        return $wizardUrl.$separator.'step_key='.urlencode($stepKey);
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

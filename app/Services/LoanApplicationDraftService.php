<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use Illuminate\Support\Collection;

class LoanApplicationDraftService
{
    public const DISCARD_SESSION_KEY = 'kf_discarded_drafts';

    /** @return array<string, mixed>|null */
    public function payloadForWizard(Customer $customer, ?int $loanProductId = null): ?array
    {
        $draft = $this->find($customer, $loanProductId);

        if (! $draft || $draft->phase === 'browse') {
            return null;
        }

        return $this->formatPayload($customer, $draft);
    }

    /** @return array{phase: string, step_key: string|null, step: int, reason: string|null} */
    public function resumeTarget(Customer $customer, LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        if (! $product) {
            return ['phase' => 'browse', 'step_key' => null, 'step' => 0, 'reason' => 'missing_product'];
        }

        $payload = $draft->payload ?? [];
        if (filled($draft->draft_reference)) {
            $payload['draft_reference'] = $draft->draft_reference;
        }
        $stepKey = $payload['step_key'] ?? null;
        $step = (int) $draft->step;
        $applicationStarted = (bool) ($payload['application_started'] ?? $draft->phase === 'application');

        if ($draft->phase === 'details' && ! $applicationStarted && ! $stepKey && $step === 0) {
            return [
                'phase' => 'details',
                'step_key' => null,
                'step' => 0,
                'reason' => 'readiness_review',
            ];
        }

        $wizardSteps = collect(app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values();

        $hasSignature = filled($payload['borrower_signature']['signature_data'] ?? null);
        // Prefer the saved step when present so locale reloads / draft restores
        // do not skip guarantor → review just because a signature was already drawn.
        if ($hasSignature && in_array($stepKey, [null, '', 'signature', 'submit'], true)) {
            $submitIndex = $wizardSteps->search(fn (array $step) => $step['key'] === 'submit');
            if ($submitIndex !== false) {
                return [
                    'phase' => 'application',
                    'step_key' => 'submit',
                    'step' => (int) $submitIndex,
                    'reason' => null,
                ];
            }
        }

        $resumeIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, $step);
        $resumeIndex = $this->clampResumeIndexToIncompleteSetup($wizardSteps, $resumeIndex, $payload, $product, $customer);
        $resumeStep = $wizardSteps[$resumeIndex] ?? null;

        return [
            'phase' => 'application',
            'step_key' => $resumeStep['key'] ?? $stepKey,
            'step' => $resumeIndex,
            'reason' => null,
        ];
    }

    /**
     * Never resume past incomplete product setup (e.g. stale drafts left on guarantor
     * when the application fee was zero / already paid).
     *
     * @param  Collection<int, array{key: string}>  $wizardSteps
     * @param  array<string, mixed>  $payload
     */
    private function clampResumeIndexToIncompleteSetup(
        Collection $wizardSteps,
        int $resumeIndex,
        array $payload,
        LoanProduct $product,
        ?Customer $customer = null,
    ): int {
        $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
        $group = is_array($payload['group'] ?? null) ? $payload['group'] : [];
        $forcedKey = null;

        if (is_group_loan_product($product)) {
            $name = trim((string) ($group['name'] ?? ''));
            $target = (int) ($group['target_member_count'] ?? 0);
            $members = is_array($group['members'] ?? null) ? $group['members'] : [];

            if ($name === '' || $target < 1) {
                $forcedKey = 'group_setup';
            } elseif (trim((string) ($group['purpose'] ?? $form['purpose'] ?? '')) === '') {
                $forcedKey = 'group_setup';
            } elseif (count($members) < $target) {
                $forcedKey = 'group_members';
            } else {
                $amountPerMember = (float) ($group['amount_per_member'] ?? 0);
                $tenure = (int) ($form['requested_tenure_months'] ?? 0);
                if ($amountPerMember < 1000 || $tenure < 1) {
                    $forcedKey = 'quote';
                }
            }
        } elseif (strtoupper((string) $product->code) === 'AB') {
            $assetIds = $form['customer_asset_ids'] ?? null;
            if (! is_array($assetIds) || $assetIds === []) {
                $single = (int) ($form['customer_asset_id'] ?? 0);
                $assetIds = $single > 0 ? [$single] : [];
            }
            $amount = (float) ($form['requested_amount'] ?? 0);
            $purpose = trim((string) ($form['purpose'] ?? ''));
            $tenure = (int) ($form['requested_tenure_months'] ?? 0);

            if ($assetIds === [] || $amount < 1000 || $purpose === '' || $tenure < 1) {
                $forcedKey = 'asset_details';
            }
        } elseif (is_marketplace_loan_product($product->code)) {
            $amount = (float) ($form['requested_amount'] ?? 0);
            $tenure = (int) ($form['requested_tenure_months'] ?? 0);
            if ($amount < 1000 || $tenure < 1) {
                $forcedKey = 'asset_tenure';
            }
        } else {
            $amount = (float) ($form['requested_amount'] ?? 0);
            $purpose = trim((string) ($form['purpose'] ?? ''));
            if ($purpose === '' && $product->hasFixedPurpose()) {
                $purpose = (string) $product->fixedPurposeKey();
            }
            $tenure = (int) ($form['requested_tenure_months'] ?? 0);
            if ($amount < 1000 || $purpose === '' || $tenure < 1) {
                $forcedKey = 'quote';
            }
        }

        $feeService = app(ApplicationFeePaymentService::class);
        $feeSatisfied = $customer
            ? $feeService->isSatisfiedFor($customer, $product, $payload)
            : false;
        $resumeKey = (string) ($wizardSteps[$resumeIndex]['key'] ?? $payload['step_key'] ?? '');
        $setupKeys = ['quote', 'asset_details', 'asset_tenure', 'group_setup', 'group_members'];
        $postFeeResume = $resumeKey !== ''
            && (
                $feeService->blocksWizardStep($resumeKey)
                || ! in_array($resumeKey, $setupKeys, true)
            );

        // Verified fee already unlocked a post-fee stage — do not rewind to Collateral/Group Setup/Quote.
        if ($forcedKey && ! ($feeSatisfied && $postFeeResume)) {
            $forcedIndex = $wizardSteps->search(fn (array $step) => ($step['key'] ?? '') === $forcedKey);
            if ($forcedIndex === false) {
                return 0;
            }
            $resumeIndex = min($resumeIndex, (int) $forcedIndex);
        }

        // Unpaid application fee: do not resume on guarantor/review/submit.
        // Land on the last setup step so the wizard opens the shared fee → payments.show gate (IL path).
        if ($customer && ! $feeSatisfied) {
            $lastSetupIndex = null;
            foreach ($wizardSteps as $i => $step) {
                if (in_array($step['key'] ?? '', $setupKeys, true)) {
                    $lastSetupIndex = (int) $i;
                }
            }
            if ($lastSetupIndex !== null && $resumeIndex > $lastSetupIndex) {
                return $lastSetupIndex;
            }
        }

        return $resumeIndex;
    }

    /** @return array<string, mixed> */
    private function formatPayload(Customer $customer, LoanApplicationDraft $draft): array
    {
        $payload = $draft->payload ?? [];
        $draftReference = $draft->draft_reference
            ?: ($payload['draft_reference'] ?? null);

        return [
            'phase' => $draft->phase,
            'step' => (int) $draft->step,
            'step_key' => $payload['step_key'] ?? null,
            'draft_reference' => $draftReference,
            'application_started' => (bool) ($payload['application_started'] ?? $draft->phase === 'application'),
            'resume_target' => $this->resumeTarget($customer, $draft),
            'loan_product_id' => $draft->loan_product_id,
            'asset_reservation_id' => $draft->asset_reservation_id,
            'asset_substep' => $payload['asset_substep'] ?? data_get($payload, 'form.asset_substep'),
            'form' => $payload['form'] ?? [],
            'inputs' => $payload['inputs'] ?? [],
            'guarantor_lookup' => $payload['guarantor_lookup'] ?? null,
            'application_fee' => $payload['application_fee'] ?? null,
            'valuation_fee' => $payload['valuation_fee'] ?? null,
            'asset_documents' => $payload['asset_documents'] ?? [],
            'education_documents' => $payload['education_documents'] ?? [],
            'institution_payment' => $payload['institution_payment'] ?? null,
            'external_guarantor' => $this->refreshExternalGuarantorPayload($customer, $payload['external_guarantor'] ?? null),
            'internal_guarantor' => $payload['internal_guarantor'] ?? null,
            'borrower_signature' => $payload['borrower_signature'] ?? null,
            'declaration_accepted' => (bool) ($payload['declaration_accepted'] ?? false),
            'group' => $payload['group'] ?? null,
            'draft_reference' => $draft->draft_reference,
        ];
    }

    /** @return array{url: string, product_name: string, phase: string, step: int}|null */
    public function resumeSummary(Customer $customer): ?array
    {
        $latest = $this->listForCustomer($customer)->first();

        return $latest ? $this->summarizeDraft($latest, $customer) : null;
    }

    /** @param  array<string, mixed>|null  $external */
    private function refreshExternalGuarantorPayload(Customer $customer, ?array $external): ?array
    {
        if (! is_array($external) || empty($external['invitation_id'])) {
            return $external;
        }

        $invitation = GuarantorInvitation::query()
            ->where('id', (int) $external['invitation_id'])
            ->where('customer_id', $customer->id)
            ->first();

        if (! $invitation) {
            return $external;
        }

        $fresh = app(GuarantorInvitationService::class)->sharePayload($invitation, $customer);

        return array_merge($external, $fresh);
    }

    /** @return array{url: string, product_name: string, phase: string, step: int, saved_at: string|null} */
    public function summarizeDraft(LoanApplicationDraft $draft, ?Customer $customer = null): array
    {
        $customer = $customer ?? $draft->customer ?? Customer::find($draft->customer_id);
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);

        return [
            'url' => $this->applicationsListUrl(),
            'product_name' => $product?->name ?? __('borrower.apply.title'),
            'phase' => $draft->phase,
            'step' => (int) $draft->step,
            'saved_at' => optional($draft->saved_at)->diffForHumans(),
        ];
    }

    public function applicationsListUrl(): string
    {
        return route('site.borrower.loans', ['tab' => 'applications']);
    }

    public function resumeUrl(Customer $customer, LoanApplicationDraft $draft): string
    {
        return route('site.borrower.loan-profile.draft', $draft);
    }

    /** @param  array{phase?: string, step_key?: string|null, step?: int, reason?: string|null}  $target */
    public function wizardApplyUrl(LoanApplicationDraft $draft, array $target = []): string
    {
        $params = array_filter([
            'product' => $draft->loan_product_id,
            'reservation' => $draft->asset_reservation_id,
            'resume' => 1,
            'phase' => $target['phase'] ?? null,
            'step' => array_key_exists('step', $target) ? (int) $target['step'] : null,
            'step_key' => $target['step_key'] ?? null,
            'return_to' => $target['return_to'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('site.borrower.apply', $params);
    }

    /**
     * Build a resume wizard URL that jumps to a specific step without duplicating query params.
     * Edit hops always return to the loan profile (not the full wizard path / fee gate).
     *
     * @param  array{phase?: string, step_key?: string|null, step?: int, reason?: string|null, return_to?: string|null}  $baseTarget
     */
    public function wizardApplyUrlForStep(LoanApplicationDraft $draft, string $stepKey, array $baseTarget = []): string
    {
        $target = $baseTarget;
        // Prefer explicit return_to; otherwise send edit hops back to the loan profile.
        if (! isset($target['return_to']) || $target['return_to'] === '' || $target['return_to'] === null) {
            $target['return_to'] = 'profile';
        }
        $target['step_key'] = $stepKey;
        unset($target['step']);

        return $this->wizardApplyUrl($draft, $target);
    }

    /**
     * Amount/terms step for "Edit amount": quote when present, otherwise the product setup step.
     * Group loans keep group_setup (identity) then quote (amount per member) — edit amount uses quote.
     *
     * @param  list<array{key: string}>  $stepPlan
     */
    public function quoteLikeStepKey(array $stepPlan): ?string
    {
        $keys = collect($stepPlan)->pluck('key')->all();
        foreach (['quote', 'asset_details', 'asset_tenure', 'group_setup'] as $candidate) {
            if (in_array($candidate, $keys, true)) {
                return $candidate;
            }
        }

        return null;
    }

    public function lastSetupStepKeyForProduct(LoanProduct $product): string
    {
        if (is_group_loan_product($product)) {
            return 'quote';
        }
        if (strtoupper((string) $product->code) === 'AB') {
            return 'asset_details';
        }
        if (is_marketplace_loan_product($product->code)) {
            return 'asset_tenure';
        }

        return 'quote';
    }

    /**
     * Wizard step where Continue opens the application-fee payment gate.
     * Cancel/failed payment returns here — never to earlier unfinished pre-Quote screens.
     */
    public function feeOriginStepKeyForProduct(LoanProduct $product): string
    {
        return $this->lastSetupStepKeyForProduct($product);
    }

    /** @param  array<string, mixed>  $payload */
    public function shouldClampToFeeGate(Customer $customer, LoanProduct $product, array $payload): bool
    {
        $draft = $this->find($customer, $product->id);
        if ($draft && filled($draft->draft_reference)) {
            $payload['draft_reference'] = $draft->draft_reference;
        }

        $stepKey = (string) ($payload['step_key'] ?? '');
        if ($stepKey === '' || ! app(ApplicationFeePaymentService::class)->blocksWizardStep($stepKey)) {
            return false;
        }

        return ! app(ApplicationFeePaymentService::class)->isSatisfiedFor($customer, $product, $payload);
    }

    /**
     * All in-progress wizard drafts and fee-pending applications.
     *
     * @return list<array{type: string, label: string, detail: string, url: string, saved_at: string|null}>
     */
    public function listResumable(Customer $customer): array
    {
        $items = [];

        foreach ($this->listForCustomer($customer) as $draft) {
            $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
            $feePending = $product && ! app(ApplicationFeePaymentService::class)->isSatisfiedFor(
                $customer,
                $product,
                $draft->payload ?? [],
            );

            $items[] = [
                'type' => 'wizard_draft',
                'label' => $product?->name ?? __('borrower.apply.title'),
                'detail' => $feePending
                    ? __('borrower.applications_list.draft_fee_pending')
                    : __('borrower.applications_list.draft_in_progress'),
                'url' => route('site.borrower.loan-profile.draft', $draft),
                'saved_at' => optional($draft->saved_at)->diffForHumans(),
            ];
        }

        return $items;
    }

    /** @return Collection<int, LoanApplicationDraft> */
    public function listForCustomer(Customer $customer): Collection
    {
        $convertedReferences = LoanApplication::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('application_number')
            ->pluck('application_number')
            ->filter()
            ->all();

        return LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->whereIn('phase', ['details', 'application'])
            ->with('product')
            ->orderByDesc('saved_at')
            ->get()
            ->reject(function (LoanApplicationDraft $draft) use ($convertedReferences) {
                $reference = (string) ($draft->draft_reference ?? '');

                // A converted spine (same number as a submitted/withdrawn application)
                // is listed from loan_applications, not as a second draft card.
                return $reference !== '' && in_array($reference, $convertedReferences, true);
            })
            ->values();
    }

    /** @param array<string, mixed> $data */
    public function save(Customer $customer, array $data): ?LoanApplicationDraft
    {
        $phase = (string) ($data['phase'] ?? 'browse');
        $productId = $data['loan_product_id'] ?? null;

        if ($productId && $this->wasDiscarded((int) $productId) && empty($data['resume_discarded'])) {
            return null;
        }

        if ($phase === 'browse' || ! $productId) {
            if ($productId) {
                $this->clear($customer, (int) $productId);
            } else {
                $this->clearAll($customer);
            }

            return null;
        }

        $existing = $this->find($customer, (int) $productId);
        $incomingInputs = is_array($data['inputs'] ?? null) ? $data['inputs'] : [];
        $existingInputs = is_array($existing?->payload['inputs'] ?? null) ? $existing->payload['inputs'] : [];
        $mergedInputs = $existingInputs;
        $incomingRegion = $incomingInputs['product_question[farming_region]'] ?? null;
        $existingRegion = $existingInputs['product_question[farming_region]'] ?? null;
        $regionChanged = $incomingRegion !== null
            && (string) $incomingRegion !== (string) ($existingRegion ?? '');
        foreach ($incomingInputs as $key => $value) {
            $isProductQuestion = is_string($key) && str_starts_with($key, 'product_question[');
            $trimmed = is_string($value) ? trim($value) : $value;
            if ($isProductQuestion && ($trimmed === '' || $trimmed === null)
                && filled($existingInputs[$key] ?? null)) {
                // Region change must be allowed to clear an incompatible district.
                if ($regionChanged && $key === 'product_question[farming_district]') {
                    $mergedInputs[$key] = $value;

                    continue;
                }
                continue;
            }
            $mergedInputs[$key] = $value;
        }
        $payload = [
            'form' => $data['form'] ?? ($existing?->payload['form'] ?? []),
            'inputs' => $mergedInputs !== [] ? $mergedInputs : ($data['inputs'] ?? ($existing?->payload['inputs'] ?? [])),
            'step_key' => $data['step_key'] ?? ($existing?->payload['step_key'] ?? null),
            'application_started' => $phase === 'application'
                || (bool) ($data['application_started'] ?? ($existing?->payload['application_started'] ?? false)),
            'guarantor_lookup' => $data['guarantor_lookup'] ?? ($existing?->payload['guarantor_lookup'] ?? null),
            'application_fee' => $data['application_fee'] ?? ($existing?->payload['application_fee'] ?? null),
            'valuation_fee' => $data['valuation_fee'] ?? ($existing?->payload['valuation_fee'] ?? null),
            'asset_documents' => $data['asset_documents'] ?? ($existing?->payload['asset_documents'] ?? []),
            'education_documents' => $data['education_documents'] ?? ($existing?->payload['education_documents'] ?? []),
            'institution_payment' => $this->normalizeInstitutionPayment(
                $data['institution_payment'] ?? ($existing?->payload['institution_payment'] ?? null)
            ),
            'external_guarantor' => array_key_exists('external_guarantor', $data)
                ? $data['external_guarantor']
                : ($existing?->payload['external_guarantor'] ?? null),
            'internal_guarantor' => array_key_exists('internal_guarantor', $data)
                ? $data['internal_guarantor']
                : ($existing?->payload['internal_guarantor'] ?? null),
            'borrower_signature' => $data['borrower_signature'] ?? ($existing?->payload['borrower_signature'] ?? null),
            'declaration_accepted' => array_key_exists('declaration_accepted', $data)
                ? (bool) $data['declaration_accepted']
                : (bool) ($existing?->payload['declaration_accepted'] ?? false),
            'group' => $data['group'] ?? ($existing?->payload['group'] ?? null),
            'asset_substep' => $data['asset_substep']
                ?? data_get($data, 'form.asset_substep')
                ?? ($existing?->payload['asset_substep'] ?? null),
        ];

        $product = LoanProduct::find((int) $productId);
        $draftReference = $data['draft_reference']
            ?? $existing?->draft_reference;

        if (filled($draftReference)) {
            $payload['draft_reference'] = $draftReference;
        }

        if (! $draftReference && $product) {
            $draftReference = app(ReferenceNumberService::class)->applicationReference($product);
            $payload['draft_reference'] = $draftReference;
        }

        // Locked product purpose is authoritative — persist before fee/resume gates run.
        if ($product?->hasFixedPurpose()) {
            $fixed = (string) $product->fixedPurposeKey();
            $form = is_array($payload['form'] ?? null) ? $payload['form'] : [];
            $form['purpose'] = $fixed;
            $form['purpose_other'] = '';
            $payload['form'] = $form;
            if (is_array($payload['group'] ?? null)) {
                $payload['group']['purpose'] = $fixed;
            }
        }

        // Never let a stale client fee state wipe a verified current-draft payment.
        // Do not call syncDraftFromVerifiedPayment here — that also advances the step and
        // would break intentional Back-to-Quote while the fee remains paid.
        if ($product) {
            $fees = app(ApplicationFeePaymentService::class);
            $existingFee = is_array($existing?->payload['application_fee'] ?? null)
                ? $existing->payload['application_fee']
                : null;
            $incomingFee = is_array($payload['application_fee'] ?? null) ? $payload['application_fee'] : null;
            $existingPaid = in_array((string) ($existingFee['status'] ?? ''), ['paid', 'waived'], true);
            $incomingPaid = in_array((string) ($incomingFee['status'] ?? ''), ['paid', 'waived'], true);
            if ($existingPaid && ! $incomingPaid) {
                $payload['application_fee'] = $existingFee;
            } elseif (! $incomingPaid && $fees->isSatisfiedFor($customer, $product, $payload)) {
                $payment = $fees->obligation($customer, $product, $payload)['payment'] ?? null;
                if ($payment && in_array($payment->status, ['paid', 'verified'], true)) {
                    $payload['application_fee'] = [
                        'status' => 'paid',
                        'reference' => $payment->reference,
                        'payment_id' => $payment->id,
                        'channel' => $payment->payment_method === 'mobile_money' ? 'mobile_money' : 'bank',
                        'amount' => (int) round((float) $payment->amount),
                        'paid_at' => ($payment->paid_at ?? now())->toIso8601String(),
                    ];
                } elseif ($existingPaid) {
                    $payload['application_fee'] = $existingFee;
                }
            }
        }

        $step = (int) ($data['step'] ?? 0);
        if ($product && $this->shouldClampToFeeGate($customer, $product, $payload)) {
            $setupKey = $this->lastSetupStepKeyForProduct($product);
            if ($setupKey) {
                $payload['step_key'] = $setupKey;
            }
            $plan = app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product);
            $index = collect($plan)->search(fn (array $step) => ($step['key'] ?? null) === ($payload['step_key'] ?? null));
            $step = $index === false ? 0 : (int) $index;
        }

        $draft = LoanApplicationDraft::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'loan_product_id' => (int) $productId,
            ],
            [
                'draft_reference' => $draftReference,
                // Navigation / autosave must never detach a marketplace reservation.
                // Only discard/clear may drop the FK; null/missing client values keep the existing one.
                'asset_reservation_id' => ($data['asset_reservation_id'] ?? null) ?: $existing?->asset_reservation_id,
                'phase' => $phase,
                'step' => $step,
                'payload' => $payload,
                'saved_at' => now(),
            ],
        );

        if ($product && app(GroupLendingService::class)->isGroupProduct($product) && is_array($payload['group'] ?? null)) {
            app(GroupMemberInvitationService::class)->syncDraftContextForLeader(
                $customer,
                $product,
                $draft,
                $payload['group'],
                is_array($payload['form'] ?? null) ? $payload['form'] : [],
            );
        }

        return $draft;
    }

    /** @param array<string, mixed> $feeState */
    public function saveApplicationFee(Customer $customer, int $loanProductId, array $feeState): LoanApplicationDraft
    {
        if ($blocked = $this->discardedWriteGuard($customer, $loanProductId)) {
            return $blocked;
        }

        $product = LoanProduct::find($loanProductId);
        $draft = $this->find($customer, $loanProductId)
            ?? new LoanApplicationDraft([
                'customer_id' => $customer->id,
                'loan_product_id' => $loanProductId,
                'phase' => 'application',
                'step' => 0,
                'payload' => [],
            ]);

        if (! $draft->draft_reference && $product) {
            $draft->draft_reference = app(ReferenceNumberService::class)->applicationReference($product);
        }

        $payload = $draft->payload ?? [];
        $payload['application_fee'] = $feeState;

        $draft->fill([
            'phase' => 'application',
            'payload' => $payload,
            'saved_at' => now(),
        ])->save();

        return $draft;
    }

    /**
     * After application fee is confirmed, move the draft onto the next wizard stage
     * for this product plan so resume does not fall back to the setup step.
     */
    public function advancePastApplicationFee(Customer $customer, int $loanProductId, ?string $stepKey = null): LoanApplicationDraft
    {
        if ($blocked = $this->discardedWriteGuard($customer, $loanProductId)) {
            return $blocked;
        }

        $product = LoanProduct::find($loanProductId);
        $draft = $this->find($customer, $loanProductId)
            ?? new LoanApplicationDraft([
                'customer_id' => $customer->id,
                'loan_product_id' => $loanProductId,
                'phase' => 'application',
                'step' => 0,
                'payload' => [],
            ]);

        if (! $product) {
            return $draft;
        }

        $payload = $draft->payload ?? [];
        $amount = (float) (
            $payload['form']['amount']
            ?? $payload['inputs']['amount']
            ?? $payload['form']['requested_amount']
            ?? $payload['inputs']['requested_amount']
            ?? 0
        );
        $nextKey = $stepKey ?: app(ApplicationFeePaymentService::class)
            ->nextStepAfterApplicationFee($customer, $product, $payload);

        $plan = app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product, $amount);
        $index = collect($plan)->search(fn (array $step) => ($step['key'] ?? null) === $nextKey);
        if ($index === false) {
            $index = (int) $draft->step;
        }

        $payload['step_key'] = $nextKey;
        $payload['application_started'] = true;

        if (! $draft->draft_reference) {
            $draft->draft_reference = app(ReferenceNumberService::class)->applicationReference($product);
        }

        $draft->fill([
            'phase' => 'application',
            'step' => (int) $index,
            'payload' => $payload,
            'saved_at' => now(),
        ])->save();

        return $draft;
    }

    /** @param array<string, mixed> $feeState */
    public function saveValuationFee(Customer $customer, int $loanProductId, array $feeState): LoanApplicationDraft
    {
        if ($blocked = $this->discardedWriteGuard($customer, $loanProductId)) {
            return $blocked;
        }

        $product = LoanProduct::find($loanProductId);
        $draft = $this->find($customer, $loanProductId)
            ?? new LoanApplicationDraft([
                'customer_id' => $customer->id,
                'loan_product_id' => $loanProductId,
                'phase' => 'application',
                'step' => 0,
                'payload' => [],
            ]);

        if (! $draft->draft_reference && $product) {
            $draft->draft_reference = app(ReferenceNumberService::class)->applicationReference($product);
        }

        $payload = $draft->payload ?? [];
        $payload['valuation_fee'] = $feeState;

        $draft->fill([
            'payload' => $payload,
            'saved_at' => now(),
        ])->save();

        return $draft;
    }

    /**
     * Do not resurrect a discarded product as a paid/advanced draft.
     * Returns an unsaved stand-in when no row remains.
     */
    private function discardedWriteGuard(Customer $customer, int $loanProductId): ?LoanApplicationDraft
    {
        if (! $this->wasDiscarded($loanProductId)) {
            return null;
        }

        return $this->find($customer, $loanProductId)
            ?? new LoanApplicationDraft([
                'customer_id' => $customer->id,
                'loan_product_id' => $loanProductId,
                'phase' => 'application',
                'step' => 0,
                'payload' => [],
            ]);
    }

    public function discard(Customer $customer, ?int $loanProductId = null): void
    {
        $draft = $this->find($customer, $loanProductId);
        if ($draft) {
            app(ApplicationFeePaymentService::class)->abandonOpenFeePaymentsForDraft(
                $customer,
                $draft->loan_product_id ? (int) $draft->loan_product_id : $loanProductId,
                $draft->draft_reference,
            );
        } elseif ($loanProductId) {
            app(ApplicationFeePaymentService::class)->abandonOpenFeePaymentsForDraft(
                $customer,
                $loanProductId,
                null,
            );
        }

        $this->clear($customer, $loanProductId);
        if ($loanProductId) {
            $this->rememberDiscard($loanProductId);
        }
    }

    public function wasDiscarded(int $loanProductId): bool
    {
        return in_array($loanProductId, $this->discardedProductIds(), true);
    }

    public function forgetDiscard(int $loanProductId): void
    {
        $ids = array_values(array_filter(
            $this->discardedProductIds(),
            fn (int $id) => $id !== $loanProductId
        ));
        session([self::DISCARD_SESSION_KEY => $ids]);
    }

    public function rememberDiscard(int $loanProductId): void
    {
        $ids = $this->discardedProductIds();
        $ids[] = $loanProductId;
        session([self::DISCARD_SESSION_KEY => array_values(array_unique($ids))]);
    }

    /** @return list<int> */
    public function discardedProductIds(): array
    {
        return array_values(array_unique(array_map(
            'intval',
            session(self::DISCARD_SESSION_KEY, [])
        )));
    }

    public function clear(Customer $customer, ?int $loanProductId = null): void
    {
        $query = LoanApplicationDraft::query()->where('customer_id', $customer->id);

        if ($loanProductId) {
            $query->where('loan_product_id', $loanProductId);
        }

        $query->delete();
    }

    public function clearAll(Customer $customer): void
    {
        LoanApplicationDraft::query()->where('customer_id', $customer->id)->delete();
    }

    public function find(Customer $customer, ?int $loanProductId = null): ?LoanApplicationDraft
    {
        $query = LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->with('product');

        if ($loanProductId) {
            $query->where('loan_product_id', $loanProductId);
        }

        return $loanProductId
            ? $query->first()
            : $query->whereIn('phase', ['details', 'application'])->orderByDesc('saved_at')->first();
    }

    /** Human-readable wizard position for admin dashboards. */
    public function progressLabel(LoanApplicationDraft $draft): string
    {
        if ($draft->phase === 'details') {
            return __('admin.application_drafts.phase_details');
        }

        $customer = $draft->relationLoaded('customer') ? $draft->customer : $draft->customer()->first();
        $product = $draft->relationLoaded('product') ? $draft->product : $draft->product()->first();

        if (! $customer || ! $product) {
            return __('admin.application_drafts.phase_application').' · '.__('admin.application_drafts.step_n', ['n' => (int) $draft->step + 1]);
        }

        $steps = collect(app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values();

        $index = max(0, min((int) $draft->step, max(0, $steps->count() - 1)));
        $label = $steps[$index]['label'] ?? __('admin.application_drafts.step_n', ['n' => $index + 1]);
        $total = max(1, $steps->count());

        return $label.' ('.($index + 1).'/'.$total.')';
    }

    /** Operational draft status key — same rules as statusBadge(). */
    public function statusKey(LoanApplicationDraft $draft): string
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        $customer = $draft->customer ?? Customer::find($draft->customer_id);
        $feePending = $customer && $product && ! app(ApplicationFeePaymentService::class)->isSatisfiedFor(
            $customer,
            $product,
            $draft->payload ?? [],
        );

        if ($feePending) {
            return 'fee_pending';
        }

        if ($draft->phase === 'details') {
            return 'browsing';
        }

        $external = ($draft->payload ?? [])['external_guarantor'] ?? null;
        if (is_array($external) && ! empty($external['invitation_url']) && empty($external['approved'])) {
            return 'awaiting_guarantor';
        }

        return 'in_progress';
    }

    /** @return array{label: string, tone: string} */
    public function statusBadge(LoanApplicationDraft $draft): array
    {
        return match ($this->statusKey($draft)) {
            'fee_pending' => ['label' => __('admin.application_drafts.status_fee_pending'), 'tone' => 'amber'],
            'browsing' => ['label' => __('admin.application_drafts.status_browsing'), 'tone' => 'gray'],
            'awaiting_guarantor' => ['label' => __('admin.application_drafts.status_awaiting_guarantor'), 'tone' => 'purple'],
            default => ['label' => __('admin.application_drafts.status_in_progress'), 'tone' => 'blue'],
        };
    }

    /**
     * Card counts for the Applications in progress queue (same incomplete set).
     *
     * @return array{total: int, browsing: int, fee_pending: int, awaiting_guarantor: int, in_progress: int}
     */
    public function incompleteStatusCounts(): array
    {
        $counts = [
            'total' => 0,
            'browsing' => 0,
            'fee_pending' => 0,
            'awaiting_guarantor' => 0,
            'in_progress' => 0,
        ];

        $drafts = LoanApplicationDraft::query()
            ->with(['customer', 'product'])
            ->whereIn('phase', ['details', 'application'])
            ->get();

        $counts['total'] = $drafts->count();
        foreach ($drafts as $draft) {
            $key = $this->statusKey($draft);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    public function requestedAmount(LoanApplicationDraft $draft): ?float
    {
        $form = ($draft->payload ?? [])['form'] ?? [];
        $amount = (float) ($form['requested_amount'] ?? 0);

        return $amount > 0 ? $amount : null;
    }

    public function countIncomplete(): int
    {
        return LoanApplicationDraft::query()
            ->whereIn('phase', ['details', 'application'])
            ->count();
    }

    /** @param  Collection<int, array{key: string, label: string}>  $wizardSteps */
    private function resolveWizardStepIndex(Collection $wizardSteps, ?string $stepKey, int $fallbackIndex): int
    {
        if ($stepKey) {
            $byKey = $wizardSteps->search(fn (array $step) => $step['key'] === $stepKey);
            if ($byKey !== false) {
                return (int) $byKey;
            }
        }

        return max(0, min($fallbackIndex, max(0, $wizardSteps->count() - 1)));
    }

    /** @return array<string, mixed> */
    public function adminSnapshot(LoanApplicationDraft $draft): array
    {
        $draft->loadMissing(['customer', 'product']);
        $customer = $draft->customer;
        $product = $draft->product;

        $profileCompletion = $customer
            ? app(ProfileCompletionService::class)->completionSummary($customer)
            : ['percent' => 0, 'remaining' => [], 'completed' => []];

        $wizardSteps = ($customer && $product)
            ? collect(app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product))
                ->reject(fn (array $step) => $step['key'] === 'product')
                ->values()
            : collect();

        $currentIndex = max(0, min((int) $draft->step, max(0, $wizardSteps->count() - 1)));
        $applicationPercent = $wizardSteps->isEmpty()
            ? 0
            : (int) round(($currentIndex / max(1, $wizardSteps->count())) * 100);

        $payload = $draft->payload ?? [];
        $customerDocuments = $customer
            ? $customer->documents()->with('documentType')->latest()->limit(10)->get()
            : collect();

        $assetMedia = $this->resolveDraftAssetMedia($payload, $customerDocuments);
        $uploadedDocuments = $assetMedia['uploaded_documents'];

        $profileSections = $customer
            ? collect(app(ProfileCompletionService::class)->calculate($customer)['sections'] ?? [])
                ->mapWithKeys(fn (array $section) => [$section['key'] => $section])
            : collect();

        $guarantor = $payload['external_guarantor'] ?? null;
        $guarantorStatus = 'Not required';
        if (is_array($guarantor) && ! empty($guarantor['invitation_url'])) {
            $guarantorStatus = ! empty($guarantor['approved'])
                ? 'Approved'
                : (filled($guarantor['status_label'] ?? null) ? (string) $guarantor['status_label'] : 'Pending');
        } elseif ($product && $customer) {
            $requiresGuarantor = app(LoanPolicyService::class)->requiresGuarantorForApplication(
                $product,
                (float) (($payload['form']['requested_amount'] ?? 0) ?: ($product->min_amount ?? 0)),
            );
            if ($requiresGuarantor) {
                $guarantorStatus = 'Not started';
            }
        }

        return [
            'profile_completion_percent' => (int) ($profileCompletion['percent'] ?? 0),
            'application_completion_percent' => $applicationPercent,
            'uploaded_documents' => $uploadedDocuments,
            'asset_photos' => $assetMedia['asset_photos'],
            'insurance_documents' => $assetMedia['insurance_documents'],
            'ownership_documents' => $assetMedia['ownership_documents'],
            'guarantor_status' => $guarantorStatus,
            'current_step' => $this->progressLabel($draft),
            'last_activity' => $draft->saved_at ?? $draft->updated_at,
            'personal' => [
                'complete' => (bool) ($profileSections['personal']['complete'] ?? false),
                'name' => $customer?->full_name,
                'phone' => $customer?->phone,
                'email' => $customer?->email,
                'nida' => $customer?->national_id,
            ],
            'kyc' => [
                'complete' => (bool) ($profileSections['face']['complete'] ?? false),
                'nida' => $customer?->nida_verification_status,
                'face' => $customer?->face_verification_status,
            ],
            'employment' => [
                'complete' => (bool) ($profileSections['activity']['complete'] ?? false),
                'type' => activity_type_label($customer?->activity_type) ?? $customer?->activity_type,
                'income' => income_range_label($customer?->income_range) ?? $customer?->income_range,
                'employer' => $customer?->business_name,
            ],
            'residence' => [
                'complete' => (bool) ($profileSections['residence']['complete'] ?? false),
                'region' => $customer?->region,
                'district' => $customer?->district,
                'street' => $customer?->street,
            ],
            'guarantor' => [
                'status' => $guarantorStatus,
                'name' => is_array($guarantor) ? ($guarantor['invitee_name'] ?? null) : null,
            ],
        ];
    }

    /** @return array{uploaded_documents: list<array<string, mixed>>, asset_photos: list<array<string, mixed>>, insurance_documents: list<array<string, mixed>>, ownership_documents: list<array<string, mixed>>} */
    private function resolveDraftAssetMedia(array $payload, $customerDocuments): array
    {
        $labels = app(AssetBackedApplyService::class)->documentLabels();
        $photoCodes = ['asset_photo_front', 'asset_photo_rear', 'asset_photo_left', 'asset_photo_right'];

        $documentIds = collect($payload['asset_documents'] ?? [])
            ->filter(fn ($doc) => is_array($doc))
            ->pluck('customer_document_id')
            ->filter()
            ->unique()
            ->values();

        $documentsById = CustomerDocument::query()
            ->whereIn('id', $documentIds)
            ->with('documentType')
            ->get()
            ->keyBy('id');

        $uploadedDocuments = [];
        $assetPhotos = [];
        $insuranceDocuments = [];
        $ownershipDocuments = [];

        foreach ($payload['asset_documents'] ?? [] as $code => $doc) {
            if (! is_array($doc)) {
                continue;
            }

            $code = (string) ($doc['code'] ?? $code);
            $entry = $this->formatDraftDocumentEntry($code, $doc, $labels, $documentsById);
            $uploadedDocuments[] = $entry;

            if (in_array($code, $photoCodes, true)) {
                $assetPhotos[] = $entry;
            } elseif ($code === 'insurance_certificate') {
                $insuranceDocuments[] = $entry;
            } elseif ($code === 'ownership_certificate') {
                $ownershipDocuments[] = $entry;
            }
        }

        foreach ($customerDocuments as $doc) {
            $path = $doc->file_path;
            $uploadedDocuments[] = [
                'code' => $doc->documentType?->code,
                'label' => $doc->documentType?->name ?? 'Document',
                'url' => $path ? asset('storage/'.$path) : null,
                'is_image' => $path ? (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', $path) : false,
            ];
        }

        return [
            'uploaded_documents' => $uploadedDocuments,
            'asset_photos' => $assetPhotos,
            'insurance_documents' => $insuranceDocuments,
            'ownership_documents' => $ownershipDocuments,
        ];
    }

    /** @param array<string, string> $labels */
    /** @param Collection<int, CustomerDocument> $documentsById */
    /** @param array<string, mixed> $doc */
    /** @return array<string, mixed> */
    private function formatDraftDocumentEntry(string $code, array $doc, array $labels, $documentsById): array
    {
        $customerDoc = isset($doc['customer_document_id'])
            ? $documentsById->get($doc['customer_document_id'])
            : null;
        $path = $doc['path'] ?? $customerDoc?->file_path;
        $url = $path ? asset('storage/'.$path) : null;

        return [
            'code' => $code,
            'label' => $doc['label'] ?? $labels[$code] ?? 'Document',
            'url' => $url,
            'is_image' => $url ? (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $path) : false,
        ];
    }

    /**
     * Institution payment destination is borrower-submitted only until ops verifies it.
     *
     * @param  array<string, mixed>|null  $destination
     * @return array<string, mixed>|null
     */
    private function normalizeInstitutionPayment(?array $destination): ?array
    {
        if (! is_array($destination) || $destination === []) {
            return $destination;
        }

        return array_merge($destination, ['verified' => false]);
    }
}

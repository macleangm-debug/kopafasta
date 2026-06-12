<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GuarantorInvitation;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;
use App\Services\ReferenceNumberService;

class LoanApplicationDraftService
{
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

        $assessment = app(LoanProductReadinessService::class)->assess($customer, $product);
        $profileIncomplete = collect($assessment['requirements'] ?? [])
            ->contains(fn (array $requirement) => empty($requirement['application_step']) && empty($requirement['complete']));

        $payload = $draft->payload ?? [];
        $stepKey = $payload['step_key'] ?? null;
        $step = (int) $draft->step;
        $applicationStarted = (bool) ($payload['application_started'] ?? $draft->phase === 'application');

        if ($profileIncomplete) {
            return [
                'phase'    => 'details',
                'step_key' => null,
                'step'     => 0,
                'reason'   => 'profile_incomplete',
            ];
        }

        if ($draft->phase === 'details' && ! $applicationStarted && ! $stepKey && $step === 0) {
            return [
                'phase'    => 'details',
                'step_key' => null,
                'step'     => 0,
                'reason'   => 'readiness_review',
            ];
        }

        $wizardSteps = collect(app(SmartLoanApplicationWizardService::class)->borrowerStepPlan($customer, $product))
            ->reject(fn (array $step) => $step['key'] === 'product')
            ->values();

        $hasSignature = filled($payload['borrower_signature']['signature_data'] ?? null);
        if ($hasSignature) {
            $submitIndex = $wizardSteps->search(fn (array $step) => $step['key'] === 'submit');
            if ($submitIndex !== false) {
                return [
                    'phase'    => 'application',
                    'step_key' => 'submit',
                    'step'     => (int) $submitIndex,
                    'reason'   => null,
                ];
            }
        }

        $resumeIndex = $this->resolveWizardStepIndex($wizardSteps, $stepKey, $step);
        $resumeStep = $wizardSteps[$resumeIndex] ?? null;

        return [
            'phase'    => 'application',
            'step_key' => $resumeStep['key'] ?? $stepKey,
            'step'     => $resumeIndex,
            'reason'   => null,
        ];
    }

    /** @return array<string, mixed> */
    private function formatPayload(Customer $customer, LoanApplicationDraft $draft): array
    {
        $payload = $draft->payload ?? [];

        return [
            'phase'                => $draft->phase,
            'step'                 => (int) $draft->step,
            'step_key'             => $payload['step_key'] ?? null,
            'application_started'  => (bool) ($payload['application_started'] ?? $draft->phase === 'application'),
            'resume_target'        => $this->resumeTarget($customer, $draft),
            'loan_product_id'      => $draft->loan_product_id,
            'asset_reservation_id' => $draft->asset_reservation_id,
            'form'                 => $payload['form'] ?? [],
            'inputs'               => $payload['inputs'] ?? [],
            'guarantor_lookup'     => $payload['guarantor_lookup'] ?? null,
            'application_fee'      => $payload['application_fee'] ?? null,
            'external_guarantor'   => $this->refreshExternalGuarantorPayload($customer, $payload['external_guarantor'] ?? null),
            'borrower_signature'   => $payload['borrower_signature'] ?? null,
            'declaration_accepted' => (bool) ($payload['declaration_accepted'] ?? false),
            'draft_reference'      => $draft->draft_reference,
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
            'url'          => $this->applicationsListUrl(),
            'product_name' => $product?->name ?? __('borrower.apply.title'),
            'phase'        => $draft->phase,
            'step'         => (int) $draft->step,
            'saved_at'     => optional($draft->saved_at)->diffForHumans(),
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
            'product'     => $draft->loan_product_id,
            'reservation' => $draft->asset_reservation_id,
            'resume'      => 1,
            'phase'       => $target['phase'] ?? null,
            'step'        => array_key_exists('step', $target) ? (int) $target['step'] : null,
            'step_key'    => $target['step_key'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return route('site.borrower.apply', $params);
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
            $fee = ($draft->payload ?? [])['application_fee'] ?? null;
            $feePending = $product && quoted_application_fee($customer, $product) > 0
                && ! app(ApplicationFeePaymentService::class)->isFeeSatisfied($fee, quoted_application_fee($customer, $product));

            $items[] = [
                'type'      => 'wizard_draft',
                'label'     => $product?->name ?? __('borrower.apply.title'),
                'detail'    => $feePending
                    ? __('borrower.applications_list.draft_fee_pending')
                    : __('borrower.applications_list.draft_in_progress'),
                'url'       => route('site.borrower.loan-profile.draft', $draft),
                'saved_at'  => optional($draft->saved_at)->diffForHumans(),
            ];
        }

        return $items;
    }

    /** @return \Illuminate\Support\Collection<int, LoanApplicationDraft> */
    public function listForCustomer(Customer $customer): \Illuminate\Support\Collection
    {
        return LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->whereIn('phase', ['details', 'application'])
            ->with('product')
            ->orderByDesc('saved_at')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function save(Customer $customer, array $data): ?LoanApplicationDraft
    {
        $phase = (string) ($data['phase'] ?? 'browse');
        $productId = $data['loan_product_id'] ?? null;

        if ($phase === 'browse' || ! $productId) {
            if ($productId) {
                $this->clear($customer, (int) $productId);
            } else {
                $this->clearAll($customer);
            }

            return null;
        }

        $existing = $this->find($customer, (int) $productId);
        $payload = [
            'form'                 => $data['form'] ?? ($existing?->payload['form'] ?? []),
            'inputs'               => $data['inputs'] ?? ($existing?->payload['inputs'] ?? []),
            'step_key'             => $data['step_key'] ?? ($existing?->payload['step_key'] ?? null),
            'application_started'  => $phase === 'application'
                || (bool) ($data['application_started'] ?? ($existing?->payload['application_started'] ?? false)),
            'guarantor_lookup'     => $data['guarantor_lookup'] ?? ($existing?->payload['guarantor_lookup'] ?? null),
            'application_fee'      => $data['application_fee'] ?? ($existing?->payload['application_fee'] ?? null),
            'external_guarantor'   => $data['external_guarantor'] ?? ($existing?->payload['external_guarantor'] ?? null),
            'borrower_signature'   => $data['borrower_signature'] ?? ($existing?->payload['borrower_signature'] ?? null),
            'declaration_accepted' => array_key_exists('declaration_accepted', $data)
                ? (bool) $data['declaration_accepted']
                : (bool) ($existing?->payload['declaration_accepted'] ?? false),
        ];

        $product = LoanProduct::find((int) $productId);
        $draftReference = $existing?->draft_reference;

        if (! $draftReference && $product) {
            $draftReference = app(ReferenceNumberService::class)->applicationReference($product);
        }

        return LoanApplicationDraft::updateOrCreate(
            [
                'customer_id'     => $customer->id,
                'loan_product_id' => (int) $productId,
            ],
            [
                'draft_reference'      => $draftReference,
                'asset_reservation_id' => $data['asset_reservation_id'] ?? null,
                'phase'                => $phase,
                'step'                 => (int) ($data['step'] ?? 0),
                'payload'              => $payload,
                'saved_at'             => now(),
            ],
        );
    }

    /** @param array<string, mixed> $feeState */
    public function saveApplicationFee(Customer $customer, int $loanProductId, array $feeState): LoanApplicationDraft
    {
        $product = LoanProduct::find($loanProductId);
        $draft = $this->find($customer, $loanProductId)
            ?? new LoanApplicationDraft([
                'customer_id'     => $customer->id,
                'loan_product_id' => $loanProductId,
                'phase'           => 'application',
                'step'            => 0,
                'payload'         => [],
            ]);

        if (! $draft->draft_reference && $product) {
            $draft->draft_reference = app(ReferenceNumberService::class)->applicationReference($product);
        }

        $payload = $draft->payload ?? [];
        $payload['application_fee'] = $feeState;

        $draft->fill([
            'payload'  => $payload,
            'saved_at' => now(),
        ])->save();

        return $draft;
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

    /** @return array{label: string, tone: string} */
    public function statusBadge(LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);
        $customer = $draft->customer ?? Customer::find($draft->customer_id);
        $fee = ($draft->payload ?? [])['application_fee'] ?? null;
        $feePending = $customer && $product && quoted_application_fee($customer, $product) > 0
            && ! app(ApplicationFeePaymentService::class)->isFeeSatisfied($fee, quoted_application_fee($customer, $product));

        if ($feePending) {
            return ['label' => __('admin.application_drafts.status_fee_pending'), 'tone' => 'amber'];
        }

        if ($draft->phase === 'details') {
            return ['label' => __('admin.application_drafts.status_browsing'), 'tone' => 'gray'];
        }

        $external = ($draft->payload ?? [])['external_guarantor'] ?? null;
        if (is_array($external) && ! empty($external['invitation_url']) && empty($external['approved'])) {
            return ['label' => __('admin.application_drafts.status_awaiting_guarantor'), 'tone' => 'purple'];
        }

        return ['label' => __('admin.application_drafts.status_in_progress'), 'tone' => 'blue'];
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

    /** @param  \Illuminate\Support\Collection<int, array{key: string, label: string}>  $wizardSteps */
    private function resolveWizardStepIndex(\Illuminate\Support\Collection $wizardSteps, ?string $stepKey, int $fallbackIndex): int
    {
        if ($stepKey) {
            $byKey = $wizardSteps->search(fn (array $step) => $step['key'] === $stepKey);
            if ($byKey !== false) {
                return (int) $byKey;
            }
        }

        return max(0, min($fallbackIndex, max(0, $wizardSteps->count() - 1)));
    }
}

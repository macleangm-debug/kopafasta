<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;

class LoanApplicationDraftService
{
    /** @return array<string, mixed>|null */
    public function payloadForWizard(Customer $customer, ?int $loanProductId = null): ?array
    {
        $draft = $this->find($customer, $loanProductId);

        if (! $draft || $draft->phase === 'browse') {
            return null;
        }

        return $this->formatPayload($draft);
    }

    /** @return array<string, mixed> */
    private function formatPayload(LoanApplicationDraft $draft): array
    {
        $payload = $draft->payload ?? [];

        return [
            'phase'                => $draft->phase,
            'step'                 => (int) $draft->step,
            'step_key'             => $payload['step_key'] ?? null,
            'application_started'  => (bool) ($payload['application_started'] ?? $draft->phase === 'application'),
            'loan_product_id'      => $draft->loan_product_id,
            'asset_reservation_id' => $draft->asset_reservation_id,
            'form'                 => $payload['form'] ?? [],
            'inputs'               => $payload['inputs'] ?? [],
            'guarantor_lookup'     => $payload['guarantor_lookup'] ?? null,
            'application_fee'      => $payload['application_fee'] ?? null,
            'external_guarantor'   => $payload['external_guarantor'] ?? null,
        ];
    }

    /** @return array{url: string, product_name: string, phase: string, step: int}|null */
    public function resumeSummary(Customer $customer): ?array
    {
        $latest = $this->listForCustomer($customer)->first();

        return $latest ? $this->summarizeDraft($latest) : null;
    }

    /** @return array{url: string, product_name: string, phase: string, step: int, saved_at: string|null} */
    public function summarizeDraft(LoanApplicationDraft $draft): array
    {
        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);

        return [
            'url'          => $this->resumeUrl($draft),
            'product_name' => $product?->name ?? __('borrower.apply.title'),
            'phase'        => $draft->phase,
            'step'         => (int) $draft->step,
            'saved_at'     => optional($draft->saved_at)->diffForHumans(),
        ];
    }

    public function resumeUrl(LoanApplicationDraft $draft): string
    {
        $params = array_filter([
            'product'     => $draft->loan_product_id,
            'reservation' => $draft->asset_reservation_id,
            'resume'      => 1,
        ]);

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
                'url'       => $this->resumeUrl($draft),
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
        ];

        return LoanApplicationDraft::updateOrCreate(
            [
                'customer_id'     => $customer->id,
                'loan_product_id' => (int) $productId,
            ],
            [
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
        $draft = $this->find($customer, $loanProductId)
            ?? new LoanApplicationDraft([
                'customer_id'     => $customer->id,
                'loan_product_id' => $loanProductId,
                'phase'           => 'application',
                'step'            => 0,
                'payload'         => [],
            ]);

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
}

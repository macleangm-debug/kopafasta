<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplicationDraft;
use App\Models\LoanProduct;

class LoanApplicationDraftService
{
    /** @return array<string, mixed>|null */
    public function payloadForWizard(Customer $customer): ?array
    {
        $draft = $this->find($customer);

        if (! $draft || $draft->phase === 'browse') {
            return null;
        }

        return [
            'phase'                => $draft->phase,
            'step'                 => (int) $draft->step,
            'loan_product_id'      => $draft->loan_product_id,
            'asset_reservation_id' => $draft->asset_reservation_id,
            'form'                 => $draft->payload['form'] ?? [],
            'inputs'               => $draft->payload['inputs'] ?? [],
            'guarantor_lookup'     => $draft->payload['guarantor_lookup'] ?? null,
        ];
    }

    /** @return array{url: string, product_name: string, phase: string, step: int}|null */
    public function resumeSummary(Customer $customer): ?array
    {
        $draft = $this->find($customer);

        if (! $draft || ! in_array($draft->phase, ['details', 'application'], true)) {
            return null;
        }

        $product = $draft->product ?? LoanProduct::find($draft->loan_product_id);

        return [
            'url'          => $this->resumeUrl($draft),
            'product_name' => $product?->name ?? __('borrower.apply.title'),
            'phase'        => $draft->phase,
            'step'         => (int) $draft->step,
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

    /** @param array<string, mixed> $data */
    public function save(Customer $customer, array $data): LoanApplicationDraft
    {
        $phase = (string) ($data['phase'] ?? 'browse');
        $productId = $data['loan_product_id'] ?? null;

        if ($phase === 'browse' || ! $productId) {
            $this->clear($customer);

            return new LoanApplicationDraft([
                'customer_id' => $customer->id,
                'phase'       => 'browse',
            ]);
        }

        $payload = [
            'form'             => $data['form'] ?? [],
            'inputs'           => $data['inputs'] ?? [],
            'guarantor_lookup' => $data['guarantor_lookup'] ?? null,
        ];

        return LoanApplicationDraft::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'loan_product_id'      => $productId,
                'asset_reservation_id' => $data['asset_reservation_id'] ?? null,
                'phase'                => $phase,
                'step'                 => (int) ($data['step'] ?? 0),
                'payload'              => $payload,
                'saved_at'             => now(),
            ],
        );
    }

    public function clear(Customer $customer): void
    {
        LoanApplicationDraft::query()->where('customer_id', $customer->id)->delete();
    }

    public function find(Customer $customer): ?LoanApplicationDraft
    {
        return LoanApplicationDraft::query()
            ->where('customer_id', $customer->id)
            ->with('product')
            ->first();
    }
}

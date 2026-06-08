<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Setting;

class IncomeProofService
{
    /** @var list<string> */
    public const PRIMARY_CODES = ['bank_statement', 'mobile_money_statement', 'mpesa_statement'];

    /** @var list<string> */
    public const OPTIONAL_CODES = ['salary_slip', 'employment_contract', 'income_statement'];

    public function isRequired(): bool
    {
        return (bool) (Setting::group('kyc')['require_income_proof'] ?? false);
    }

    public function hasPrimaryProof(Customer $customer): bool
    {
        return CustomerDocument::query()
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', fn ($q) => $q->whereIn('code', self::PRIMARY_CODES))
            ->exists();
    }

    public function primaryDocument(Customer $customer): ?CustomerDocument
    {
        return CustomerDocument::with('documentType')
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', fn ($q) => $q->whereIn('code', self::PRIMARY_CODES))
            ->latest()
            ->first();
    }

    /** @return \Illuminate\Support\Collection<int, CustomerDocument> */
    public function uploadedDocuments(Customer $customer): \Illuminate\Support\Collection
    {
        $codes = array_merge(self::PRIMARY_CODES, self::OPTIONAL_CODES);

        return CustomerDocument::with('documentType')
            ->where('customer_id', $customer->id)
            ->whereHas('documentType', fn ($q) => $q->whereIn('code', $codes))
            ->latest()
            ->get()
            ->unique('document_type_id');
    }

    public function satisfiesRequirement(Customer $customer): bool
    {
        if ($this->hasPrimaryProof($customer)) {
            return true;
        }

        if (! $this->isRequired()) {
            return app(ProfileCompletionService::class)->isActivityComplete($customer);
        }

        return false;
    }

    /**
     * @return list<array{key: string, label: string, required: bool, complete: bool, document: CustomerDocument|null}>
     */
    public function checklist(Customer $customer): array
    {
        $uploads = $this->uploadedDocuments($customer)->keyBy(fn ($doc) => $doc->documentType?->code);

        $items = [
            [
                'key'      => 'bank_statement',
                'label'    => __('borrower.profile.income_bank_statement'),
                'required' => true,
                'complete' => $uploads->has('bank_statement'),
                'document' => $uploads->get('bank_statement'),
            ],
            [
                'key'      => 'mobile_money_statement',
                'label'    => __('borrower.profile.income_mobile_money_statement'),
                'required' => true,
                'complete' => $uploads->has('mobile_money_statement') || $uploads->has('mpesa_statement'),
                'document' => $uploads->get('mobile_money_statement') ?? $uploads->get('mpesa_statement'),
            ],
            [
                'key'      => 'salary_slip',
                'label'    => __('borrower.profile.income_salary_slip'),
                'required' => false,
                'complete' => $uploads->has('salary_slip'),
                'document' => $uploads->get('salary_slip'),
            ],
            [
                'key'      => 'employment_contract',
                'label'    => __('borrower.profile.employment_contract'),
                'required' => false,
                'complete' => $uploads->has('employment_contract'),
                'document' => $uploads->get('employment_contract'),
            ],
        ];

        return $items;
    }
}

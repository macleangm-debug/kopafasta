<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerDocument;
use Illuminate\Support\Collection;

class ProfileDocumentService
{
    /** @var array<string, list<string>> */
    private const CODE_ALIASES = [
        'residence_letter' => ['residence_letter', 'address_proof'],
        'mobile_money_statement' => ['mobile_money_statement', 'mpesa_statement'],
    ];

    /** @param  list<string>  $codes */
    public function latestByCodes(Customer $customer, array $codes): Collection
    {
        if ($codes === []) {
            return collect();
        }

        $resolvedCodes = $this->expandCodes($codes);
        $docs = $this->queryDocuments($customer, $resolvedCodes, profileOnly: true);

        if ($docs->isEmpty()) {
            $docs = $this->queryDocuments($customer, $resolvedCodes, profileOnly: false);
        }

        return $docs
            ->unique('document_type_id')
            ->mapWithKeys(function (CustomerDocument $doc) {
                $code = (string) $doc->documentType?->code;

                return [$this->canonicalCode($code) => $doc];
            });
    }

    public function latestProfileDocument(Customer $customer, string $code): ?CustomerDocument
    {
        return $this->latestByCodes($customer, [$code])->get($this->canonicalCode($code));
    }

    public function has(Customer $customer, string $code): bool
    {
        return app(ProfileValidationService::class)->hasDocument($customer, $code);
    }

    public function hasProfileDocument(Customer $customer, string $code): bool
    {
        return $this->latestProfileDocument($customer, $code) !== null;
    }

    public function statusLabel(CustomerDocument $document): string
    {
        return match ($document->status) {
            'verified', 'approved' => __('borrower.profile.document_status.approved'),
            'rejected'             => __('borrower.profile.document_status.rejected'),
            default                => __('borrower.profile.document_status.pending'),
        };
    }

    /** @return array<string, mixed> */
    public function metadata(CustomerDocument $document): array
    {
        if (! filled($document->notes)) {
            return [];
        }

        $decoded = json_decode((string) $document->notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param  list<string>  $codes */
    private function expandCodes(array $codes): array
    {
        return collect($codes)
            ->flatMap(fn (string $code) => self::CODE_ALIASES[$code] ?? [$code])
            ->unique()
            ->values()
            ->all();
    }

    private function canonicalCode(string $code): string
    {
        foreach (self::CODE_ALIASES as $canonical => $aliases) {
            if (in_array($code, $aliases, true)) {
                return $canonical;
            }
        }

        return $code;
    }

    /** @param  list<string>  $codes */
    private function queryDocuments(Customer $customer, array $codes, bool $profileOnly): Collection
    {
        return CustomerDocument::query()
            ->with('documentType')
            ->where('customer_id', $customer->id)
            ->when($profileOnly, fn ($query) => $query->whereNull('loan_application_id'))
            ->whereHas('documentType', fn ($query) => $query->whereIn('code', $codes))
            ->orderByDesc('id')
            ->get();
    }
}

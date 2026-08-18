<?php

namespace App\Services;

use App\Models\Setting;

class LoanRejectionReasonService
{
    /** @return list<array{code: string, label: string, category: string}> */
    public function defaults(): array
    {
        return [
            ['code' => 'identity_verification_failed', 'label' => 'Identity Verification Failed', 'category' => 'Identity & KYC'],
            ['code' => 'national_id_mismatch', 'label' => 'National ID Mismatch', 'category' => 'Identity & KYC'],
            ['code' => 'incomplete_kyc', 'label' => 'Incomplete KYC', 'category' => 'Identity & KYC'],
            ['code' => 'face_verification_failed', 'label' => 'Face Verification Failed', 'category' => 'Identity & KYC'],
            ['code' => 'fraud_suspected', 'label' => 'Fraud Suspected', 'category' => 'Identity & KYC'],
            ['code' => 'residence_cannot_be_verified', 'label' => 'Residence Could Not Be Verified', 'category' => 'Residence'],
            ['code' => 'residence_letter_missing', 'label' => 'Residence Letter Missing or Invalid', 'category' => 'Residence'],
            ['code' => 'address_mismatch', 'label' => 'Address Details Do Not Match', 'category' => 'Residence'],
            ['code' => 'poor_crb_history', 'label' => 'Poor CRB History', 'category' => 'Credit'],
            ['code' => 'excessive_existing_debt', 'label' => 'Excessive Existing Debt', 'category' => 'Credit'],
            ['code' => 'active_loan_delinquency', 'label' => 'Active Loan Delinquency', 'category' => 'Credit'],
            ['code' => 'low_credit_score', 'label' => 'Low Credit Score', 'category' => 'Credit'],
            ['code' => 'insufficient_income', 'label' => 'Insufficient Income', 'category' => 'Affordability'],
            ['code' => 'repayment_exceeds_limit', 'label' => 'Repayment Exceeds Affordability Limit', 'category' => 'Affordability'],
            ['code' => 'unstable_income_pattern', 'label' => 'Unstable Income Pattern', 'category' => 'Affordability'],
            ['code' => 'required_documents_missing', 'label' => 'Required Documents Missing', 'category' => 'Documentation'],
            ['code' => 'documents_not_verified', 'label' => 'Documents Could Not Be Verified', 'category' => 'Documentation'],
            ['code' => 'falsified_documentation', 'label' => 'Falsified Documentation', 'category' => 'Documentation'],
            ['code' => 'inconsistent_information', 'label' => 'Inconsistent Information', 'category' => 'Documentation'],
            ['code' => 'insurance_type_mismatch', 'label' => 'Insurance Type Mismatch (e.g. claimed Comprehensive, actual Third Party)', 'category' => 'Collateral'],
            ['code' => 'employment_not_verified', 'label' => 'Employment Could Not Be Verified', 'category' => 'Employment / Business'],
            ['code' => 'business_not_verified', 'label' => 'Business Activity Could Not Be Verified', 'category' => 'Employment / Business'],
            ['code' => 'business_too_new', 'label' => 'Business Too New', 'category' => 'Employment / Business'],
            ['code' => 'guarantor_not_acceptable', 'label' => 'Guarantor Not Acceptable', 'category' => 'Guarantor'],
            ['code' => 'guarantor_profile_incomplete', 'label' => 'Guarantor Profile Incomplete', 'category' => 'Guarantor'],
            ['code' => 'collateral_insufficient', 'label' => 'Collateral Insufficient or Unverified', 'category' => 'Collateral'],
            ['code' => 'product_eligibility_not_met', 'label' => 'Product Eligibility Not Met', 'category' => 'Internal Policy'],
            ['code' => 'internal_credit_policy_declined', 'label' => 'Internal Credit Policy Declined', 'category' => 'Internal Policy'],
        ];
    }

    /** @return list<array{code: string, label: string, category: string}> */
    public function all(): array
    {
        $defaults = collect($this->defaults())->keyBy('code');
        $configured = Setting::get('rejection.reasons');

        $rows = $defaults;
        if (is_array($configured) && $configured !== []) {
            foreach ($configured as $row) {
                if (! filled($row['code'] ?? null)) {
                    continue;
                }
                $code = (string) $row['code'];
                $rows[$code] = [
                    'code' => $code,
                    'label' => $row['label'] ?? ($defaults[$code]['label'] ?? $code),
                    'category' => $row['category'] ?? ($defaults[$code]['category'] ?? 'Internal Policy'),
                ];
            }
            // Keep any new default codes that older settings lists omitted
            foreach ($defaults as $code => $default) {
                if (! $rows->has($code)) {
                    $rows[$code] = $default;
                }
            }
        }

        return $rows
            ->map(fn (array $row) => [
                'code' => $row['code'],
                'label' => $this->labelForCode($row['code']) ?: $row['label'],
                'category' => $this->categoryLabel($row['category'] ?? 'Internal Policy'),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, list<array{code: string, label: string, category: string}>> */
    public function grouped(): array
    {
        return collect($this->all())
            ->groupBy(fn (array $row) => $row['category'])
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    public function labelForCode(?string $code, ?string $locale = null): ?string
    {
        if (! filled($code)) {
            return null;
        }

        $key = 'rejection.reasons.'.$code;
        $translated = $locale
            ? __($key, [], $locale)
            : __($key);

        if ($translated !== $key) {
            return $translated;
        }

        foreach ($this->defaults() as $reason) {
            if ($reason['code'] === $code) {
                return $reason['label'];
            }
        }

        $configured = Setting::get('rejection.reasons');
        if (is_array($configured)) {
            foreach ($configured as $row) {
                if (($row['code'] ?? null) === $code && filled($row['label'] ?? null)) {
                    return (string) $row['label'];
                }
            }
        }

        return null;
    }

    public function adviceLabel(?string $code, ?string $locale = null): ?string
    {
        if (! filled($code) || $code === 'custom') {
            return null;
        }

        $key = 'rejection.advice.'.$code;
        $translated = $locale ? __($key, [], $locale) : __($key);

        return $translated !== $key ? $translated : null;
    }

    /** @return array<string, string|null> */
    public function adviceOptions(?string $locale = null): array
    {
        $options = [];
        foreach (array_keys(trans('rejection.advice', [], $locale ?? app()->getLocale()) ?: []) as $code) {
            if ($code === 'custom') {
                $options[$code] = 'Custom advice (write below)';
                continue;
            }
            $options[$code] = $this->adviceLabel($code, $locale) ?? $code;
        }

        return $options;
    }

    public function resolveBorrowerAdvice(?string $code, ?string $custom, ?string $locale = null): ?string
    {
        $parts = [];

        if ($code && $code !== 'custom') {
            $preset = $this->adviceLabel($code, $locale);
            if ($preset) {
                $parts[] = $preset;
            }
        }

        $custom = trim((string) $custom);
        if ($custom !== '') {
            $parts[] = $custom;
        }

        if ($parts === []) {
            return null;
        }

        return implode(' ', array_unique($parts));
    }

    public function categoryLabel(string $category, ?string $locale = null): string
    {
        $key = 'rejection.categories.'.$category;
        $translated = $locale ? __($key, [], $locale) : __($key);

        return $translated !== $key ? $translated : $category;
    }

    public function isValidCode(?string $code): bool
    {
        if (! filled($code)) {
            return false;
        }

        if (collect($this->defaults())->contains(fn (array $row) => $row['code'] === $code)) {
            return true;
        }

        $configured = Setting::get('rejection.reasons');

        return is_array($configured)
            && collect($configured)->contains(fn ($row) => ($row['code'] ?? null) === $code);
    }

    /**
     * @param  list<string|null>|null  $codes
     * @return list<string>
     */
    public function normalizeCodes(?array $codes, ?string $fallbackCode = null): array
    {
        $normalized = collect($codes ?? [])
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalized === [] && filled($fallbackCode)) {
            $normalized = [trim((string) $fallbackCode)];
        }

        return array_values(array_filter(
            $normalized,
            fn (string $code) => $this->isValidCode($code),
        ));
    }

    /**
     * @param  list<string>|null  $codes
     * @return list<string>
     */
    public function labelsForCodes(?array $codes, ?string $locale = null, ?string $fallbackCode = null): array
    {
        $codes = $this->normalizeCodes($codes, $fallbackCode);

        return collect($codes)
            ->map(fn (string $code) => $this->labelForCode($code, $locale))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>|null  $codes
     */
    public function formatReasonsForBorrower(?array $codes, ?string $fallbackCode = null, ?string $fallbackLabel = null, ?string $locale = null): string
    {
        $labels = $this->labelsForCodes($codes, $locale, $fallbackCode);

        if ($labels === []) {
            $labels = array_values(array_filter([(string) $fallbackLabel]));
        }

        if ($labels === []) {
            return __('borrower.applications_list.rejected_default', [], $locale ?? app()->getLocale());
        }

        return implode('; ', $labels);
    }

    /**
     * Borrower-facing rejection reasons from the platform catalog (Settings + lang),
     * plus any extra file-specific narrative (capacity numbers, officer note).
     *
     * @param  list<string>|null  $codes
     * @return array{codes: list<string>, labels: list<string>, summary: string, detail: ?string}
     */
    public function reasonsForLetter(
        ?array $codes,
        ?string $fallbackCode = null,
        ?string $fallbackLabel = null,
        ?string $locale = null,
    ): array {
        $normalized = $this->normalizeCodes($codes, $fallbackCode);
        $labels = $this->labelsForCodes($normalized, $locale, $fallbackCode);
        $detail = trim((string) $fallbackLabel);
        $englishJoin = implode('; ', $this->labelsForCodes($normalized, 'en', $fallbackCode));
        $currentJoin = implode('; ', $labels);

        if ($labels === [] && $detail !== '') {
            $labels = [$detail];
            $detail = null;
        } elseif ($detail !== '' && (
            strcasecmp($detail, $currentJoin) === 0
            || strcasecmp($detail, $englishJoin) === 0
        )) {
            $detail = null;
        } elseif ($detail === '') {
            $detail = null;
        }

        if ($labels === []) {
            $labels = [__('borrower.applications_list.rejected_default', [], $locale ?? app()->getLocale())];
        }

        return [
            'codes' => $normalized,
            'labels' => array_values($labels),
            'summary' => implode('; ', $labels),
            'detail' => $detail !== null && $detail !== '' ? $detail : null,
        ];
    }
}

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
            ['code' => 'fraud_suspected', 'label' => 'Fraud Suspected', 'category' => 'Identity & KYC'],
            ['code' => 'poor_crb_history', 'label' => 'Poor CRB History', 'category' => 'Credit'],
            ['code' => 'excessive_existing_debt', 'label' => 'Excessive Existing Debt', 'category' => 'Credit'],
            ['code' => 'active_loan_delinquency', 'label' => 'Active Loan Delinquency', 'category' => 'Credit'],
            ['code' => 'low_credit_score', 'label' => 'Low Credit Score', 'category' => 'Credit'],
            ['code' => 'insufficient_income', 'label' => 'Insufficient Income', 'category' => 'Affordability'],
            ['code' => 'repayment_exceeds_limit', 'label' => 'Repayment Exceeds Affordability Limit', 'category' => 'Affordability'],
            ['code' => 'unstable_income_pattern', 'label' => 'Unstable Income Pattern', 'category' => 'Affordability'],
            ['code' => 'required_documents_missing', 'label' => 'Required Documents Missing', 'category' => 'Documentation'],
            ['code' => 'documents_not_verified', 'label' => 'Documents Could Not Be Verified', 'category' => 'Documentation'],
            ['code' => 'inconsistent_information', 'label' => 'Inconsistent Information', 'category' => 'Documentation'],
            ['code' => 'employment_not_verified', 'label' => 'Employment Could Not Be Verified', 'category' => 'Employment / Business'],
            ['code' => 'business_not_verified', 'label' => 'Business Activity Could Not Be Verified', 'category' => 'Employment / Business'],
            ['code' => 'business_too_new', 'label' => 'Business Too New', 'category' => 'Employment / Business'],
            ['code' => 'product_eligibility_not_met', 'label' => 'Product Eligibility Not Met', 'category' => 'Internal Policy'],
            ['code' => 'internal_credit_policy_declined', 'label' => 'Internal Credit Policy Declined', 'category' => 'Internal Policy'],
        ];
    }

    /** @return list<array{code: string, label: string, category: string}> */
    public function all(): array
    {
        $configured = Setting::get('rejection.reasons');

        if (is_array($configured) && $configured !== []) {
            return collect($configured)
                ->filter(fn ($row) => filled($row['code'] ?? null) && filled($row['label'] ?? null))
                ->values()
                ->all();
        }

        return $this->defaults();
    }

    /** @return array<string, list<array{code: string, label: string, category: string}>> */
    public function grouped(): array
    {
        return collect($this->all())
            ->groupBy(fn (array $row) => $row['category'])
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    public function labelForCode(?string $code): ?string
    {
        if (! filled($code)) {
            return null;
        }

        foreach ($this->all() as $reason) {
            if ($reason['code'] === $code) {
                return $reason['label'];
            }
        }

        return null;
    }

    public function isValidCode(?string $code): bool
    {
        return filled($code) && collect($this->all())->contains(fn (array $row) => $row['code'] === $code);
    }
}

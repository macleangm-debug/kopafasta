<?php

namespace App\Services;

use App\Models\Setting;

class LegalSettingsService
{
    public function get(string $key, mixed $default = null): mixed
    {
        $legal = Setting::get("legal.$key");

        if ($legal !== null && $legal !== '') {
            return $legal;
        }

        // Legacy keys stored under company.* before Legal settings existed.
        return match ($key) {
            'signatory_name'  => Setting::get('company.signatory_name', $default),
            'signatory_title' => Setting::get('company.signatory_title', $default),
            'signature_path'  => Setting::get('company.signature_path', $default),
            default           => $default,
        };
    }

    public function signatoryName(): ?string
    {
        $name = trim((string) ($this->get('signatory_name') ?? ''));

        return $name !== '' ? $name : null;
    }

    public function signatoryTitle(): ?string
    {
        $title = trim((string) ($this->get('signatory_title') ?? ''));

        return $title !== '' ? $title : null;
    }

    public function signatureFilesystemPath(): ?string
    {
        $path = $this->get('signature_path');

        if (! $path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim((string) $path, '/'));

        return is_file($full) ? $full : null;
    }

    public function stampFilesystemPath(): ?string
    {
        $path = $this->get('stamp_path');

        if (! $path) {
            return null;
        }

        $full = storage_path('app/public/'.ltrim((string) $path, '/'));

        return is_file($full) ? $full : null;
    }

    public function offerValidityDays(): int
    {
        return max(1, (int) $this->get('offer_validity_days', 14));
    }

    public function jurisdiction(): string
    {
        return (string) $this->get('jurisdiction', 'United Republic of Tanzania');
    }

    /** @return array<string, mixed> */
    public function contractClauses(): array
    {
        $penaltyRate = (float) Setting::get('loan.default_penalty_rate', 1);
        $graceDays = (int) Setting::get('loan.default_grace_days', 7);
        $penaltyCap = (float) Setting::get('loan.penalty_cap_percent', 30);
        $penaltyBasis = (string) Setting::get('loan.penalty_basis', 'per_day');
        $lateFee = (float) $this->get('late_fee_amount', 2000);

        $basisLabel = match ($penaltyBasis) {
            'per_month' => 'per month',
            'one_time'  => 'one-time',
            default     => 'per day',
        };

        return [
            'penalty_rate'        => $penaltyRate,
            'penalty_rate_label'  => format_number($penaltyRate, 2).'% '.$basisLabel.' on overdue balance',
            'grace_days'          => $graceDays,
            'penalty_cap_percent' => $penaltyCap,
            'late_fee'            => $lateFee,
            'late_fee_label'      => format_money($lateFee),
            'collection_charge'   => (string) $this->get('collection_fee_text', 'Actual cost incurred'),
            'legal_recovery'      => (string) $this->get('legal_recovery_text', 'Borrower responsible for all legal recovery costs'),
            'jurisdiction'        => $this->jurisdiction(),
            'default_clause'      => (string) $this->get('default_clause', 'Failure to pay any instalment by the due date constitutes default after the grace period.'),
            'collection_clause'   => (string) $this->get('collection_clause', 'The lender may contact the borrower by phone, SMS, email, or in person to recover overdue amounts.'),
            'recovery_clause'     => (string) $this->get('recovery_clause', 'Persistent default may result in legal recovery action and reporting to credit reference bureaus.'),
            'penalty_clause'      => (string) $this->get('penalty_clause', 'Penalty interest and late fees apply as stated in the schedule of charges.'),
            'legal_cost_clause'   => (string) $this->get('legal_cost_clause', 'The borrower shall bear all reasonable legal costs incurred in recovering overdue amounts.'),
            'guarantor_clause'    => (string) $this->get('guarantor_clause', 'Where a guarantor has signed, they become jointly and severally liable for repayment.'),
        ];
    }
}

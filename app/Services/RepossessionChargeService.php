<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplicationAsset;
use App\Models\Setting;

class RepossessionChargeService
{
    /** @return array<string, array<string, mixed>> */
    public function assetTypes(): array
    {
        $defaults = config('repossession_charges.asset_types', []);
        $stored = Setting::get('repossession.charges');

        if (! is_array($stored)) {
            return $defaults;
        }

        $merged = $defaults;
        foreach ($stored as $key => $row) {
            if (is_array($row)) {
                $merged[$key] = array_merge($defaults[$key] ?? [], $row);
            }
        }

        return $merged;
    }

    public function assetTypeLabel(string $type): string
    {
        return (string) ($this->assetTypes()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type)));
    }

    /**
     * @return array{partner_amount: float, company_amount: float, total_charge: float, asset_type: ?string, manual_quote: bool}
     */
    public function calculate(string $assetType, ?float $manualPartnerCost = null): array
    {
        $types = $this->assetTypes();
        $row = $types[$assetType] ?? null;

        if (! $row) {
            return [
                'partner_amount' => 0.0,
                'company_amount' => 0.0,
                'total_charge'   => 0.0,
                'asset_type'     => $assetType,
                'manual_quote'   => false,
            ];
        }

        $manualQuote = (bool) ($row['manual_quote'] ?? false);
        $markupPercent = (float) ($row['markup_percent'] ?? 10);
        $partnerCost = $manualPartnerCost ?? ($row['partner_cost'] !== null ? (float) $row['partner_cost'] : null);

        if ($partnerCost === null || $partnerCost <= 0) {
            return [
                'partner_amount' => 0.0,
                'company_amount' => 0.0,
                'total_charge'   => 0.0,
                'asset_type'     => $assetType,
                'manual_quote'   => $manualQuote,
            ];
        }

        $partnerAmount = round($partnerCost, 2);
        $companyAmount = round($partnerAmount * ($markupPercent / 100), 2);

        return [
            'partner_amount' => $partnerAmount,
            'company_amount' => $companyAmount,
            'total_charge'   => round($partnerAmount + $companyAmount, 2),
            'asset_type'       => $assetType,
            'manual_quote'     => $manualQuote,
        ];
    }

    public function assetTypeForLoan(Loan $loan): ?string
    {
        $application = $loan->application;
        if (! $application) {
            return null;
        }

        $asset = LoanApplicationAsset::query()
            ->where('loan_application_id', $application->id)
            ->first();

        return $asset?->asset_type;
    }

    /**
     * @return array{partner_amount: float, company_amount: float, total_charge: float, asset_type: ?string, manual_quote: bool}|null
     */
    public function calculateForLoan(Loan $loan, ?float $manualPartnerCost = null): ?array
    {
        $assetType = $this->assetTypeForLoan($loan);
        if (! $assetType) {
            return null;
        }

        $charge = $this->calculate($assetType, $manualPartnerCost);
        if ($charge['total_charge'] <= 0) {
            return null;
        }

        return $charge;
    }

    /** @return array<string, mixed> */
    public function settingsForForm(): array
    {
        return [
            'asset_types' => $this->assetTypes(),
            'values'      => Setting::group('repossession'),
        ];
    }
}

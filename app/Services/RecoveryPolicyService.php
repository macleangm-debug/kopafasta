<?php

namespace App\Services;

use App\Models\RecoveryAssignment;
use App\Models\Setting;
use App\Models\Vendor;

class RecoveryPolicyService
{
    /** @return array<string, array<string, mixed>> */
    public function partnerTypes(): array
    {
        return config('recovery.partner_types', []);
    }

    public function partnerTypeLabel(string $type): string
    {
        return (string) ($this->partnerTypes()[$type]['label'] ?? ucfirst(str_replace('_', ' ', $type)));
    }

    public function vendorCategoryForType(string $type): ?string
    {
        return $this->partnerTypes()[$type]['vendor_category'] ?? null;
    }

    public function gracePeriodDays(): int
    {
        return max(1, (int) Setting::get('recovery.grace_period_days', 2));
    }

    public function gracePeriodDaysForLoan(?\App\Models\Loan $loan): int
    {
        if (! $loan) {
            return $this->gracePeriodDays();
        }

        $loan->loadMissing('product');

        return max(1, (int) app(LoanPenaltyPolicy::class)->for($loan)['default_grace_days']);
    }

    public function feeBase(): string
    {
        $base = (string) Setting::get('recovery.fee_base', config('recovery.default_fee_base', 'principal'));

        return in_array($base, ['principal', 'outstanding'], true) ? $base : 'principal';
    }

    public function feeTypeForPartnerType(string $type): string
    {
        $stored = Setting::get("recovery.fee_type.{$type}");
        if (in_array($stored, ['percentage', 'fixed'], true)) {
            return $stored;
        }

        return (string) (config("recovery.partner_types.{$type}.default_fee_type") ?? 'percentage');
    }

    public function fixedAmountForType(string $type): ?float
    {
        $stored = Setting::get("recovery.fixed_amount.{$type}");
        if ($stored !== null && $stored !== '') {
            return (float) $stored;
        }

        $default = config("recovery.partner_types.{$type}.default_fixed_amount");

        return $default !== null ? (float) $default : null;
    }

    public function slaDaysForType(string $type): int
    {
        $key = "recovery.sla_days.{$type}";
        $stored = Setting::get($key);

        if ($stored !== null && $stored !== '') {
            return max(1, (int) $stored);
        }

        return max(1, (int) ($this->partnerTypes()[$type]['default_sla_days'] ?? 7));
    }

    public function defaultCommissionPercent(string $type): float
    {
        $key = "recovery.commission_percent.{$type}";
        $stored = Setting::get($key);

        if ($stored !== null && $stored !== '') {
            return (float) $stored;
        }

        return (float) ($this->partnerTypes()[$type]['default_commission_percent'] ?? 0);
    }

    public function defaultMarkupPercent(string $type): float
    {
        $key = "recovery.markup_percent.{$type}";
        $stored = Setting::get($key);

        if ($stored !== null && $stored !== '') {
            return (float) $stored;
        }

        return (float) ($this->partnerTypes()[$type]['default_markup_percent'] ?? 0);
    }

    public function autoEscalate(): bool
    {
        return (bool) Setting::get('recovery.auto_escalate', true);
    }

    public function priorityForType(string $type): int
    {
        $stored = Setting::get("recovery.priority.{$type}");

        if ($stored !== null && $stored !== '') {
            return max(1, (int) $stored);
        }

        return max(1, (int) ($this->partnerTypes()[$type]['default_priority'] ?? 99));
    }

    public function loanTypesScopeForType(string $type): string
    {
        $stored = Setting::get("recovery.loan_types.{$type}");

        if ($stored === null || $stored === '') {
            return (string) ($this->partnerTypes()[$type]['default_loan_types'] ?? 'all');
        }

        return (string) $stored;
    }

    public function collateralScopeForType(string $type): string
    {
        $stored = Setting::get("recovery.collateral_scope.{$type}");
        $default = (string) ($this->partnerTypes()[$type]['default_collateral_scope'] ?? 'all');

        return in_array($stored, ['all', 'secured', 'unsecured'], true) ? $stored : $default;
    }

    public function autoEscalateForType(string $type): bool
    {
        $stored = Setting::get("recovery.auto_escalate_type.{$type}");

        if ($stored !== null && $stored !== '') {
            return (bool) $stored;
        }

        return (bool) ($this->partnerTypes()[$type]['default_auto_escalate'] ?? $this->autoEscalate());
    }

    public function partnerTypeAppliesToLoan(string $type, \App\Models\Loan $loan): bool
    {
        $loan->loadMissing('product', 'application.collateralAsset');

        $loanTypes = trim($this->loanTypesScopeForType($type));
        if ($loanTypes !== '' && strtolower($loanTypes) !== 'all') {
            $allowed = collect(explode(',', strtoupper($loanTypes)))
                ->map(fn (string $code) => trim($code))
                ->filter()
                ->all();
            $productCode = strtoupper((string) ($loan->product?->code ?? ''));

            if ($productCode !== '' && ! in_array($productCode, $allowed, true)) {
                return false;
            }
        }

        $isSecured = (bool) ($loan->product?->requires_collateral ?? false)
            || $loan->application?->collateralAsset !== null;

        return match ($this->collateralScopeForType($type)) {
            'secured'   => $isSecured,
            'unsecured' => ! $isSecured,
            default     => true,
        };
    }

    /** @return list<string> */
    public function partnerTypesForLoan(\App\Models\Loan $loan): array
    {
        return collect(array_keys($this->partnerTypes()))
            ->filter(fn (string $type) => $this->partnerTypeAppliesToLoan($type, $loan))
            ->sortBy(fn (string $type) => $this->priorityForType($type))
            ->values()
            ->all();
    }

    public function autoAssignCallCenter(): bool
    {
        return (bool) Setting::get('recovery.auto_assign_call_center', true);
    }

    public function callCenterLeadDays(): int
    {
        return max(0, (int) Setting::get('recovery.call_center_lead_days', 2));
    }

    /**
     * Commission is calculated from original outstanding or principal only (not compounded).
     * Supports percentage or fixed partner fee with percentage markup on fixed amount.
     *
     * @return array{partner_amount: float, company_amount: float, total_charge: float}
     */
    public function calculateRecoveryCharge(
        float $baseAmount,
        string $partnerType,
        ?float $commissionPercent = null,
        ?float $markupPercent = null,
        ?Vendor $vendor = null,
    ): array {
        $feeType = $vendor?->recovery_fee_type ?? $this->feeTypeForPartnerType($partnerType);
        $commissionPercent ??= (float) ($vendor?->recovery_commission_percent ?? $this->defaultCommissionPercent($partnerType));
        $markupPercent ??= (float) ($vendor?->recovery_markup_percent ?? $this->defaultMarkupPercent($partnerType));

        if ($feeType === 'fixed') {
            $fixed = (float) ($vendor?->recovery_fixed_amount ?? $this->fixedAmountForType($partnerType) ?? 0);
            $partnerAmount = round(max(0, $fixed), 2);
            $companyAmount = round($partnerAmount * ($markupPercent / 100), 2);
        } else {
            $partnerAmount = round($baseAmount * ($commissionPercent / 100), 2);
            $companyAmount = round($baseAmount * ($markupPercent / 100), 2);
        }

        return [
            'partner_amount' => $partnerAmount,
            'company_amount' => $companyAmount,
            'total_charge'   => round($partnerAmount + $companyAmount, 2),
        ];
    }

    public function ratesForVendor(Vendor $vendor, string $partnerType): array
    {
        $commission = (float) ($vendor->recovery_commission_percent
            ?? $this->defaultCommissionPercent($partnerType));
        $markup = (float) ($vendor->recovery_markup_percent
            ?? $this->defaultMarkupPercent($partnerType));

        return [
            'commission_percent'     => $commission,
            'company_markup_percent' => $markup,
        ];
    }

    /** @return array<string, mixed> */
    public function settingsForForm(): array
    {
        $values = Setting::group('recovery');
        $types = $this->partnerTypes();

        return compact('values', 'types');
    }
}

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

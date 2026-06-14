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
        return max(1, (int) Setting::get('recovery.grace_period_days', 7));
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
     * Commission is calculated from original outstanding only (not compounded).
     *
     * @return array{partner_amount: float, company_amount: float, total_charge: float}
     */
    public function calculateRecoveryCharge(
        float $originalOutstanding,
        float $commissionPercent,
        float $markupPercent,
    ): array {
        $partnerAmount = round($originalOutstanding * ($commissionPercent / 100), 2);
        $companyAmount = round($originalOutstanding * ($markupPercent / 100), 2);

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

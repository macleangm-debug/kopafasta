<?php

namespace App\Services;

use App\Models\AffiliateEvent;
use App\Models\Vendor;

class AffiliateCommissionCalculatorService
{
    public function calculate(Vendor $affiliate, float $commissionBase, string $feeType): float
    {
        if ($commissionBase <= 0) {
            return 0.0;
        }

        $settings = app(AffiliateSettingsService::class);
        $mode = $settings->commissionMode();

        return match ($mode) {
            'fixed'     => round($this->fixedAmount($affiliate, $feeType), 2),
            'tiered'    => round($this->tieredAmount($affiliate, $feeType, $commissionBase), 2),
            'hybrid'    => round($this->hybridAmount($affiliate, $commissionBase, $feeType), 2),
            default     => round($commissionBase * ($this->percentFor($affiliate) / 100), 2),
        };
    }

    public function percentFor(Vendor $affiliate): float
    {
        return (float) ($affiliate->affiliate_commission_percent
            ?? app(AffiliateSettingsService::class)->forForm()['default_commission_percent']
            ?? config('affiliates.default_commission_percent', 10));
    }

    public function fixedAmount(Vendor $affiliate, string $feeType): float
    {
        $defaults = app(AffiliateSettingsService::class)->fixedCommissionAmounts();

        return (float) ($defaults[$feeType] ?? $defaults['default'] ?? 0);
    }

    public function tieredAmount(Vendor $affiliate, string $feeType, float $commissionBase): float
    {
        $count = $this->tierCount($affiliate, $feeType);
        $tiers = app(AffiliateSettingsService::class)->commissionTiers();

        foreach ($tiers as $tier) {
            $min = (int) ($tier['min_count'] ?? 1);
            $max = isset($tier['max_count']) && $tier['max_count'] !== '' && $tier['max_count'] !== null
                ? (int) $tier['max_count']
                : null;

            if ($count < $min || ($max !== null && $count > $max)) {
                continue;
            }

            $type = (string) ($tier['type'] ?? 'fixed');

            return $type === 'percentage'
                ? $commissionBase * ((float) ($tier['amount'] ?? 0) / 100)
                : (float) ($tier['amount'] ?? 0);
        }

        return $this->fixedAmount($affiliate, $feeType);
    }

    public function hybridAmount(Vendor $affiliate, float $commissionBase, string $feeType): float
    {
        $settings = app(AffiliateSettingsService::class);
        $fixed = (float) ($settings->hybridFixedAmount($affiliate) ?? 0);
        $percent = (float) ($settings->hybridPercent($affiliate) ?? $this->percentFor($affiliate));

        return $fixed + ($commissionBase * ($percent / 100));
    }

    public function tierCount(Vendor $affiliate, string $feeType): int
    {
        $eventType = match ($feeType) {
            'registration_fee' => 'registration',
            'application_fee', 'post_approval_fee' => 'application',
            default => 'registration',
        };

        return AffiliateEvent::query()
            ->where('partner_id', $affiliate->id)
            ->where('event_type', $eventType)
            ->count();
    }
}

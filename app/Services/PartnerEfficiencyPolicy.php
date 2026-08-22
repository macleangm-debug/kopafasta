<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;

class PartnerEfficiencyPolicy
{
    public const BAND_STRONG = 'strong';

    public const BAND_WATCH = 'watch';

    public const BAND_AT_RISK = 'at_risk';

    public const BAND_NEW = 'new';

    /** @return array<string, mixed> */
    public function settings(): array
    {
        $defaults = config('partners.efficiency', []);
        $stored = Setting::get('partners.efficiency');

        if (! is_array($stored)) {
            return $defaults;
        }

        return array_merge($defaults, $stored);
    }

    public function minJobsForScore(): int
    {
        return max(1, (int) ($this->settings()['min_jobs_for_score'] ?? 3));
    }

    public function strongScore(): int
    {
        return max(1, min(100, (int) ($this->settings()['strong_score'] ?? 80)));
    }

    public function watchScore(): int
    {
        $watch = (int) ($this->settings()['watch_score'] ?? 60);

        return max(1, min($this->strongScore() - 1, $watch));
    }

    public function forceAtRiskEscalationPercent(): float
    {
        return (float) ($this->settings()['force_at_risk_escalation_percent'] ?? 40);
    }

    public function forceAtRiskFailPercent(): float
    {
        return (float) ($this->settings()['force_at_risk_fail_percent'] ?? 40);
    }

    public function weightCompletion(): int
    {
        return (int) ($this->settings()['weight_completion'] ?? 40);
    }

    public function weightOnTime(): int
    {
        return (int) ($this->settings()['weight_on_time'] ?? 25);
    }

    public function weightNotEscalated(): int
    {
        return (int) ($this->settings()['weight_not_escalated'] ?? 20);
    }

    public function weightNotFailed(): int
    {
        return (int) ($this->settings()['weight_not_failed'] ?? 15);
    }

    public function autoNudge(): bool
    {
        return (bool) ($this->settings()['auto_nudge'] ?? true);
    }

    public function autoSuspend(): bool
    {
        return (bool) ($this->settings()['auto_suspend'] ?? true);
    }

    public function warningsBeforeSuspend(): int
    {
        return max(1, (int) ($this->settings()['warnings_before_suspend'] ?? 2));
    }

    public function nudgeCooldownDays(): int
    {
        return max(1, (int) ($this->settings()['nudge_cooldown_days'] ?? 7));
    }

    public function bandLabel(string $band): string
    {
        return match ($band) {
            self::BAND_STRONG => 'Strong',
            self::BAND_WATCH => 'Watch',
            self::BAND_AT_RISK => 'Needs coaching',
            default => 'New',
        };
    }

    /** @return list<string> */
    public function fieldCategories(): array
    {
        return [
            'valuer',
            'gps_installer',
            'insurance',
            'debt_collector',
            'towing',
            'auctioneer',
            'legal_partner',
            'call_center',
        ];
    }

    public function appliesTo(Partner $partner): bool
    {
        if (in_array((string) $partner->category, $this->fieldCategories(), true)) {
            return true;
        }

        return $partner->tasks()->exists() || $partner->recoveryAssignments()->exists();
    }
}

<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\Setting;
use App\Support\PartnerPerformanceStatus;
use Illuminate\Support\Carbon;

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

    public function autoRecover(): bool
    {
        return (bool) ($this->settings()['auto_recover'] ?? true);
    }

    public function warningsBeforeSuspend(): int
    {
        return max(1, (int) ($this->settings()['warnings_before_suspend'] ?? 2));
    }

    public function nudgeCooldownDays(): int
    {
        return max(1, (int) ($this->settings()['nudge_cooldown_days'] ?? 7));
    }

    public function excellentScore(): int
    {
        $excellent = (int) ($this->settings()['excellent_score'] ?? 90);

        return max($this->strongScore(), min(100, $excellent));
    }

    public function targetOnTimePercent(): float
    {
        return max(0, min(100, (float) ($this->settings()['target_on_time_percent'] ?? 90)));
    }

    public function targetCompletionPercent(): float
    {
        return max(0, min(100, (float) ($this->settings()['target_completion_percent'] ?? 95)));
    }

    public function recoverLookbackDays(): int
    {
        return max(1, min(365, (int) ($this->settings()['recover_lookback_days'] ?? 90)));
    }

    public function recoverMinScore(): int
    {
        $stored = $this->settings()['recover_min_score'] ?? null;
        if ($stored !== null && $stored !== '') {
            return max(1, min(100, (int) $stored));
        }

        return $this->watchScore();
    }

    public function nextReviewAt(?Carbon $from = null): Carbon
    {
        $from = ($from ?? now())->copy();
        $candidate = $from->copy()->next(Carbon::MONDAY)->setTime(6, 30);

        if ($from->isMonday() && $from->lt($from->copy()->setTime(6, 30))) {
            return $from->copy()->setTime(6, 30);
        }

        return $candidate;
    }

    public function bandLabel(string $band, ?string $locale = null, ?int $score = null): string
    {
        return PartnerPerformanceStatus::label($this->presentationStatusForBand($band, $score), $locale);
    }

    public function presentationStatusForBand(string $band, ?int $score = null): string
    {
        return match ($band) {
            self::BAND_STRONG => ($score !== null && $score >= $this->excellentScore())
                ? PartnerPerformanceStatus::EXCELLENT
                : PartnerPerformanceStatus::GOOD_STANDING,
            self::BAND_WATCH => PartnerPerformanceStatus::NEEDS_ATTENTION,
            self::BAND_AT_RISK => PartnerPerformanceStatus::AT_RISK,
            default => PartnerPerformanceStatus::RAMP_UP,
        };
    }

    /** Task/case partners that receive governance (Terms, eligibility, auto-recovery). */
    /** @return list<string> */
    public function governanceCategories(): array
    {
        return [
            'valuer',
            'gps_installer',
            'insurance',
            'call_center',
            'debt_collector',
            'auctioneer',
            'legal_partner',
        ];
    }

    public function isGoverned(Partner $partner): bool
    {
        return in_array((string) $partner->category, $this->governanceCategories(), true);
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
        if ($this->isGoverned($partner)) {
            return true;
        }

        return $partner->tasks()->exists() || $partner->recoveryAssignments()->exists();
    }
}

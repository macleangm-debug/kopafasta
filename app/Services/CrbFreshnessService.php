<?php

namespace App\Services;

use App\Models\CreditHistory;
use App\Models\Setting;
use Carbon\CarbonImmutable;

class CrbFreshnessService
{
    public function freshnessDays(): int
    {
        $days = (int) (Setting::group('kyc')['crb_freshness_days'] ?? 90);

        return max(30, min(365, $days));
    }

    public function referenceDate(?CreditHistory $history): ?CarbonImmutable
    {
        if (! $history?->checked_at) {
            return null;
        }

        return CarbonImmutable::parse($history->checked_at);
    }

    public function isFresh(?CreditHistory $history): bool
    {
        $reference = $this->referenceDate($history);

        if (! $reference) {
            return false;
        }

        return $reference->addDays($this->freshnessDays())->isFuture();
    }

    public function isExpired(?CreditHistory $history): bool
    {
        if (! $history?->checked_at) {
            return true;
        }

        return ! $this->isFresh($history);
    }

    public function daysSinceCheck(?CreditHistory $history): ?int
    {
        if (! $history?->checked_at) {
            return null;
        }

        return (int) CarbonImmutable::parse($history->checked_at)->diffInDays(now());
    }

    public function daysUntilExpiry(?CreditHistory $history): ?int
    {
        $reference = $this->referenceDate($history);

        if (! $reference) {
            return null;
        }

        return (int) now()->diffInDays($reference->addDays($this->freshnessDays()), false);
    }

    /** @return array{label: string, tone: string} */
    public function statusMeta(?CreditHistory $history): array
    {
        if (! $history?->checked_at) {
            return ['label' => 'Not retrieved', 'tone' => 'gray'];
        }

        if ($this->isFresh($history)) {
            return ['label' => 'Fresh', 'tone' => 'emerald'];
        }

        return ['label' => 'Expired', 'tone' => 'amber'];
    }
}

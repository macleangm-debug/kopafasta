<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use Carbon\CarbonImmutable;

class KycFreshnessService
{
    public function freshnessDays(): int
    {
        $days = (int) (Setting::group('kyc')['freshness_days'] ?? 90);

        return max(30, min(365, $days));
    }

    public function referenceDate(Customer $customer): ?CarbonImmutable
    {
        $dates = array_filter([
            $customer->kyc_reconfirmed_at,
            $customer->kyc?->verified_at,
            $customer->nida_verified_at,
            $customer->face_verified_at,
        ]);

        if ($dates === []) {
            return null;
        }

        $latest = collect($dates)->sortByDesc(fn ($d) => $d->timestamp)->first();

        return CarbonImmutable::parse($latest);
    }

    public function isStale(Customer $customer): bool
    {
        $reference = $this->referenceDate($customer);

        if (! $reference) {
            return false;
        }

        return $reference->addDays($this->freshnessDays())->isPast();
    }

    public function canApply(Customer $customer): bool
    {
        return ! $this->isStale($customer);
    }

    public function markReconfirmed(Customer $customer): void
    {
        $customer->update(['kyc_reconfirmed_at' => now()]);
    }

    public function daysUntilStale(Customer $customer): ?int
    {
        $reference = $this->referenceDate($customer);

        if (! $reference) {
            return null;
        }

        $expires = $reference->addDays($this->freshnessDays());

        return (int) CarbonImmutable::today()->diffInDays($expires, false);
    }

    /**
     * Profile section keys that must be refreshed when KYC is stale.
     * NIDA and face verification are intentionally excluded.
     *
     * @return list<string>
     */
    public function refreshSectionKeys(): array
    {
        $configured = Setting::group('kyc')['freshness_sections'] ?? null;

        if (is_array($configured) && $configured !== []) {
            return array_values($configured);
        }

        return ['activity', 'residence', 'documents'];
    }

    /** @return list<string> */
    public function sectionsDueForRefresh(Customer $customer): array
    {
        if (! $this->isStale($customer)) {
            return [];
        }

        return $this->refreshSectionKeys();
    }

    public function sectionRequiresRefresh(Customer $customer, string $sectionKey): bool
    {
        return in_array($sectionKey, $this->sectionsDueForRefresh($customer), true);
    }
}

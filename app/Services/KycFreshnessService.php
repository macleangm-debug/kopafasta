<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use Carbon\CarbonImmutable;

class KycFreshnessService
{
    /** @return array<string, int|null> section => days (null = never expires) */
    public function sectionFreshnessDays(): array
    {
        $settings = Setting::group('kyc');
        $configured = $settings['freshness_section_days'] ?? null;

        $defaults = [
            'residence' => 90,
            'activity'  => 90,
            'documents' => 90,
            'kin'       => 365,
            'face'      => null,
            'nida'      => null,
        ];

        if (! is_array($configured)) {
            $legacy = (int) ($settings['freshness_days'] ?? 90);

            return [
                'residence' => $legacy,
                'activity'  => $legacy,
                'documents' => $legacy,
                'kin'       => 365,
                'face'      => null,
                'nida'      => null,
            ];
        }

        $merged = [];
        foreach ($defaults as $section => $defaultDays) {
            $value = $configured[$section] ?? $defaultDays;
            if ($value === null || $value === '' || $value === 'never') {
                $merged[$section] = null;

                continue;
            }

            $merged[$section] = max(30, min(3650, (int) $value));
        }

        return $merged;
    }

    public function sectionFreshnessDaysFor(string $section): ?int
    {
        return $this->sectionFreshnessDays()[$section] ?? null;
    }

    /**
     * Profile section keys that may require periodic refresh.
     *
     * @return list<string>
     */
    public function refreshSectionKeys(): array
    {
        return collect($this->sectionFreshnessDays())
            ->filter(fn (?int $days) => $days !== null)
            ->keys()
            ->values()
            ->all();
    }

    public function sectionReferenceDate(Customer $customer, string $section): ?CarbonImmutable
    {
        $confirmed = $customer->profile_section_confirmed_at ?? [];

        if (! empty($confirmed[$section])) {
            return CarbonImmutable::parse($confirmed[$section]);
        }

        if (! empty($customer->kyc_reconfirmed_at) && in_array($section, ['residence', 'activity', 'documents'], true)) {
            return CarbonImmutable::parse($customer->kyc_reconfirmed_at);
        }

        return $this->inferSectionReferenceDate($customer, $section);
    }

    public function isSectionStale(Customer $customer, string $section): bool
    {
        $days = $this->sectionFreshnessDaysFor($section);

        if ($days === null) {
            return false;
        }

        if (! $this->sectionHasData($customer, $section)) {
            return false;
        }

        $reference = $this->sectionReferenceDate($customer, $section);

        if (! $reference) {
            return false;
        }

        return $reference->addDays($days)->isPast();
    }

    /** @deprecated Use isSectionStale() per section. */
    public function isStale(Customer $customer): bool
    {
        return $this->sectionsDueForRefresh($customer) !== [];
    }

    public function canApply(Customer $customer): bool
    {
        return $this->sectionsDueForRefresh($customer) === [];
    }

    /** @return list<string> */
    public function sectionsDueForRefresh(Customer $customer): array
    {
        return collect($this->refreshSectionKeys())
            ->filter(fn (string $section) => $this->isSectionStale($customer, $section))
            ->values()
            ->all();
    }

    public function sectionRequiresRefresh(Customer $customer, string $sectionKey): bool
    {
        return $this->isSectionStale($customer, $sectionKey);
    }

    public function markSectionConfirmed(Customer $customer, string $section): void
    {
        $confirmed = $customer->profile_section_confirmed_at ?? [];
        $confirmed[$section] = now()->toIso8601String();

        $customer->update(['profile_section_confirmed_at' => $confirmed]);
    }

    public function markReconfirmed(Customer $customer, array $sections = ['residence', 'activity', 'documents']): void
    {
        $confirmed = $customer->profile_section_confirmed_at ?? [];
        $now = now()->toIso8601String();

        foreach ($sections as $section) {
            $confirmed[$section] = $now;
        }

        $customer->update([
            'kyc_reconfirmed_at'            => now(),
            'profile_section_confirmed_at'  => $confirmed,
        ]);
    }

    /** @deprecated Global freshness clock — prefer markSectionConfirmed(). */
    public function daysUntilStale(Customer $customer): ?int
    {
        $sections = $this->sectionsDueForRefresh($customer);

        if ($sections === []) {
            $soonest = null;
            foreach ($this->refreshSectionKeys() as $section) {
                $reference = $this->sectionReferenceDate($customer, $section);
                $days = $this->sectionFreshnessDaysFor($section);
                if (! $reference || $days === null) {
                    continue;
                }
                $remaining = (int) CarbonImmutable::today()->diffInDays($reference->addDays($days), false);
                $soonest = $soonest === null ? $remaining : min($soonest, $remaining);
            }

            return $soonest;
        }

        return 0;
    }

    /** @deprecated */
    public function freshnessDays(): int
    {
        return (int) ($this->sectionFreshnessDays()['activity'] ?? 90);
    }

    /** @deprecated */
    public function referenceDate(Customer $customer): ?CarbonImmutable
    {
        return $this->sectionReferenceDate($customer, 'activity');
    }

    private function sectionHasData(Customer $customer, string $section): bool
    {
        $profile = app(ProfileCompletionService::class);
        $validation = app(ProfileValidationService::class);

        return match ($section) {
            'residence' => $profile->isResidenceComplete($customer),
            'activity'  => $profile->isActivityComplete($customer),
            'documents' => $profile->isDocumentsComplete($customer),
            'kin'       => $validation->isKinComplete($customer),
            default     => false,
        };
    }

    private function inferSectionReferenceDate(Customer $customer, string $section): ?CarbonImmutable
    {
        if (! $this->sectionHasData($customer, $section)) {
            return null;
        }

        $fallback = $customer->updated_at ?? $customer->created_at;

        return $fallback ? CarbonImmutable::parse($fallback) : null;
    }
}

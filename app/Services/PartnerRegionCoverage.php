<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Collection;

/**
 * Uber/Bolt-style coverage: nationwide partners cover every region;
 * regional partners only cover listed regions (empty list = no coverage).
 */
class PartnerRegionCoverage
{
    public function covers(?Vendor $vendor, ?string $region): bool
    {
        if (! $vendor) {
            return false;
        }

        if (($vendor->coverage_type ?? 'regions') === 'nationwide') {
            return true;
        }

        if (blank($region)) {
            // No borrower region yet — only nationwide partners are safe matches.
            return false;
        }

        $regions = array_values(array_filter(
            array_map('strval', $vendor->regions ?? []),
            fn (string $value) => $value !== '',
        ));

        if ($regions === []) {
            return false;
        }

        return in_array((string) $region, $regions, true);
    }

    /**
     * @param  Collection<int, Vendor>  $partners
     * @return Collection<int, Vendor>
     */
    public function filterAvailable(Collection $partners, ?string $region, bool $requireRegion = true): Collection
    {
        $available = $partners
            ->filter(fn (Vendor $vendor) => $this->covers($vendor, $region))
            ->values();

        if ($available->isNotEmpty()) {
            return $available;
        }

        // Soft fallback only when region matching is not required.
        if (! $requireRegion && filled($region)) {
            return $partners->values();
        }

        // No borrower region: allow nationwide only (already filtered); soft mode returns all.
        if (blank($region) && ! $requireRegion) {
            return $partners->values();
        }

        return $available;
    }

    /**
     * Active partners that do not cover the borrower region (enrollment gaps).
     *
     * @param  Collection<int, Vendor>  $partners
     * @return Collection<int, Vendor>
     */
    public function filterUnavailable(Collection $partners, ?string $region): Collection
    {
        return $partners
            ->reject(fn (Vendor $vendor) => $this->covers($vendor, $region))
            ->values();
    }

    public function label(Vendor $vendor): string
    {
        if (($vendor->coverage_type ?? 'regions') === 'nationwide') {
            return 'Nationwide';
        }

        $regions = array_values(array_filter($vendor->regions ?? []));

        return $regions === [] ? 'No regions set' : implode(', ', $regions);
    }
}

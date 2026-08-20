<?php

namespace App\Services;

use App\Models\LoanApplication;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class PartnerMatchingService
{
    public function __construct(
        private readonly PartnerAutoAssignSelector $selector,
        private readonly PartnerAutoAssignPolicy $autoAssign,
        private readonly PartnerRegionCoverage $coverage,
    ) {}

    /** @return Collection<int, Vendor> */
    public function allActiveValuers(): Collection
    {
        return Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'valuer')->orWhere('roles', 'like', '%"valuer"%');
            })
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, Vendor> */
    public function valuersForRegion(?string $region): Collection
    {
        $requireRegion = (bool) ($this->autoAssign->forServiceCategory('valuer')['require_region'] ?? true);

        return $this->coverage->filterAvailable($this->allActiveValuers(), $region, $requireRegion);
    }

    /** @param  list<int>  $excludeIds */
    public function suggestValuer(LoanApplication $application, array $excludeIds = []): ?Vendor
    {
        $application->loadMissing('customer');
        $region = $application->customer?->region;
        $candidates = $this->valuersForRegion($region);
        $exclude = array_map('intval', $excludeIds);
        $eligible = $candidates->reject(
            fn (Vendor $vendor) => in_array((int) $vendor->id, $exclude, true)
        )->values();

        return $this->selector->pickService('valuer', $eligible, $exclude)
            ?? $eligible->first();
    }

    /** @return list<string> */
    public function regionCoverage(Vendor $vendor): array
    {
        if (($vendor->coverage_type ?? 'regions') === 'nationwide') {
            return ['Nationwide'];
        }

        return array_values($vendor->regions ?? []);
    }
}

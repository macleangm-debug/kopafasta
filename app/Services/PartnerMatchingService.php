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
    public function valuersForRegion(?string $region): Collection
    {
        $all = Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'valuer')->orWhere('roles', 'like', '%"valuer"%');
            })
            ->orderBy('name')
            ->get();

        $requireRegion = (bool) ($this->autoAssign->forServiceCategory('valuer')['require_region'] ?? true);

        return $this->coverage->filterAvailable($all, $region, $requireRegion);
    }

    public function suggestValuer(LoanApplication $application): ?Vendor
    {
        $application->loadMissing('customer');
        $region = $application->customer?->region;
        $candidates = $this->valuersForRegion($region);

        return $this->selector->pickService('valuer', $candidates)
            ?? $candidates->first();
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

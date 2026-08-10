<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class PartnerMatchingService
{
    public function __construct(
        private readonly PartnerAutoAssignSelector $selector,
        private readonly PartnerAutoAssignPolicy $autoAssign,
    ) {}

    /** @return Collection<int, Vendor> */
    public function valuersForRegion(?string $region): Collection
    {
        $query = Vendor::query()
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('category', 'valuer')->orWhere('roles', 'like', '%"valuer"%');
            });

        if (blank($region)) {
            return $query->orderBy('name')->get();
        }

        $matches = $query->get()->filter(function (Vendor $vendor) use ($region): bool {
            if (($vendor->coverage_type ?? 'regions') === 'nationwide') {
                return true;
            }

            $regions = $vendor->regions ?? [];

            return $regions === [] || in_array($region, $regions, true);
        });

        if ($matches->isNotEmpty()) {
            return $matches->sortBy('name')->values();
        }

        if ($this->autoAssign->forServiceCategory('valuer')['require_region'] ?? true) {
            return collect();
        }

        return $query->orderBy('name')->get();
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

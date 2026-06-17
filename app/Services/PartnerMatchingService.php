<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoanApplication;
use App\Models\Vendor;
use Illuminate\Support\Collection;

class PartnerMatchingService
{
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
            $regions = $vendor->regions ?? [];

            return $regions === [] || in_array($region, $regions, true);
        });

        if ($matches->isNotEmpty()) {
            return $matches->sortBy('name')->values();
        }

        return $query->orderBy('name')->get();
    }

    public function suggestValuer(LoanApplication $application): ?Vendor
    {
        $application->loadMissing('customer');
        $region = $application->customer?->region;

        return $this->valuersForRegion($region)->first();
    }

    /** @return list<string> */
    public function regionCoverage(Vendor $vendor): array
    {
        return array_values($vendor->regions ?? []);
    }
}

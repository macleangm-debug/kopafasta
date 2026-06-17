<?php

namespace App\Services;

use App\Models\LocationCountry;
use App\Models\LocationDistrict;
use App\Models\LocationRegion;
use App\Models\LocationWard;

class LocationLookupService
{
    /** @return array<string, list<string>> */
    public function treeForCountry(string $countryCode = 'TZ'): array
    {
        $country = LocationCountry::query()
            ->where('code', strtoupper($countryCode))
            ->where('is_active', true)
            ->first();

        if (! $country) {
            return $this->configTree($countryCode);
        }

        $tree = [];
        $regions = LocationRegion::query()
            ->where('country_id', $country->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        foreach ($regions as $region) {
            $districts = LocationDistrict::query()
                ->where('region_id', $region->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name')
                ->all();

            $tree[$region->name] = $districts;
        }

        return $tree !== [] ? $tree : $this->configTree($countryCode);
    }

    /** @return list<string> */
    public function regionNames(string $countryCode = 'TZ'): array
    {
        return array_keys($this->treeForCountry($countryCode));
    }

    /** @return list<array{id: int, name: string}> */
    public function wardsForDistrictName(string $districtName, ?string $regionName = null, string $countryCode = 'TZ'): array
    {
        $country = LocationCountry::query()->where('code', strtoupper($countryCode))->first();
        if (! $country) {
            return [];
        }

        $districtQuery = LocationDistrict::query()
            ->where('name', $districtName)
            ->where('is_active', true)
            ->whereHas('region', function ($q) use ($country, $regionName): void {
                $q->where('country_id', $country->id);
                if ($regionName) {
                    $q->where('name', $regionName);
                }
            });

        $district = $districtQuery->first();
        if (! $district) {
            return [];
        }

        return LocationWard::query()
            ->where('district_id', $district->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (LocationWard $ward) => ['id' => $ward->id, 'name' => $ward->name])
            ->all();
    }

    /** @return array<string, list<string>> */
    private function configTree(string $countryCode): array
    {
        if (strtoupper($countryCode) === 'TZ') {
            return config('tanzania_locations', []);
        }

        return [];
    }
}

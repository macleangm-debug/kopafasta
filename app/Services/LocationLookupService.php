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
        $fallback = $this->configTree($countryCode);

        $country = LocationCountry::query()
            ->where('code', strtoupper($countryCode))
            ->where('is_active', true)
            ->first();

        if (! $country) {
            return $fallback;
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

            if ($districts === []) {
                $districts = $this->configDistrictsForRegion($region->name, $fallback);
            }

            $tree[$region->name] = array_values($districts);
        }

        foreach ($fallback as $name => $districts) {
            if ($this->treeHasRegion($tree, (string) $name)) {
                continue;
            }

            $tree[$name] = array_values($districts);
        }

        return $tree !== [] ? $tree : $fallback;
    }

    /** @return list<string> */
    public function regionNames(string $countryCode = 'TZ'): array
    {
        return array_keys($this->treeForCountry($countryCode));
    }

    /** @return list<string> */
    public function districtsForRegion(?string $regionName, string $countryCode = 'TZ'): array
    {
        if (! filled($regionName)) {
            return [];
        }

        $tree = $this->treeForCountry($countryCode);
        $key = $this->resolveRegionKey($tree, $regionName);

        return $key !== null ? array_values($tree[$key]) : [];
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

    /**
     * @param  array<string, list<string>>  $fallback
     * @return list<string>
     */
    private function configDistrictsForRegion(string $regionName, array $fallback): array
    {
        $key = $this->resolveRegionKey($fallback, $regionName);

        return $key !== null ? array_values($fallback[$key]) : [];
    }

    /**
     * @param  array<string, list<string>>  $tree
     */
    private function treeHasRegion(array $tree, string $regionName): bool
    {
        return $this->resolveRegionKey($tree, $regionName) !== null;
    }

    /**
     * @param  array<string, list<string>>  $tree
     */
    private function resolveRegionKey(array $tree, string $regionName): ?string
    {
        if (array_key_exists($regionName, $tree)) {
            return $regionName;
        }

        $needle = $this->normalizeName($regionName);
        foreach (array_keys($tree) as $key) {
            if ($this->normalizeName((string) $key) === $needle) {
                return (string) $key;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($name))) ?? '';
    }
}

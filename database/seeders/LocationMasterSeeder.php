<?php

namespace Database\Seeders;

use App\Models\LocationCountry;
use App\Models\LocationDistrict;
use App\Models\LocationRegion;
use Illuminate\Database\Seeder;

class LocationMasterSeeder extends Seeder
{
    public function run(): void
    {
        $countries = config('countries', []);

        foreach ($countries as $code => $meta) {
            $country = LocationCountry::query()->updateOrCreate(
                ['code' => strtoupper((string) $code)],
                ['name' => $meta['name'] ?? strtoupper((string) $code), 'is_active' => true],
            );

            if (strtoupper((string) $code) !== 'TZ') {
                continue;
            }

            foreach (config('tanzania_locations', []) as $regionName => $districts) {
                $region = LocationRegion::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name' => $regionName],
                    ['is_active' => true],
                );

                foreach ($districts as $districtName) {
                    LocationDistrict::query()->updateOrCreate(
                        ['region_id' => $region->id, 'name' => $districtName],
                        ['is_active' => true],
                    );
                }
            }
        }
    }
}

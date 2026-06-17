<?php

use App\Services\LocationLookupService;

if (! function_exists('location_tree')) {
    /** @return array<string, list<string>> */
    function location_tree(string $countryCode = 'TZ'): array
    {
        return app(LocationLookupService::class)->treeForCountry($countryCode);
    }
}

if (! function_exists('partner_region_options')) {
    /** @return list<string> */
    function partner_region_options(): array
    {
        return app(LocationLookupService::class)->regionNames('TZ');
    }
}

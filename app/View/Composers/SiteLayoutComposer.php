<?php

namespace App\View\Composers;

use App\Services\CountrySettingsService;
use Illuminate\View\View;

class SiteLayoutComposer
{
    public function __construct(private readonly CountrySettingsService $countries)
    {
    }

    public function compose(View $view): void
    {
        $view->with([
            'navProducts' => public_catalogue_products(),
            'siteLocale' => app()->getLocale(),
            'siteCountry' => strtoupper((string) session('country', 'TZ')),
            'siteCountries' => collect($this->countries->codes())
                ->map(fn (string $code) => $this->countries->forCode($code))
                ->values()
                ->all(),
        ]);
    }
}

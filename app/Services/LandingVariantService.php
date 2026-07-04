<?php

namespace App\Services;

use Illuminate\Http\Request;

class LandingVariantService
{
    public function resolve(Request $request): array
    {
        $variants = config('site_landing.variants', []);
        $default = config('site_landing.default_variant', 'a');

        if ($query = $request->query('landing')) {
            $key = strtolower((string) $query);
            if (isset($variants[$key])) {
                $request->session()->put('landing_variant', $key);
            }
        }

        $active = (string) $request->session()->get('landing_variant', $default);
        if (! isset($variants[$active])) {
            $active = $default;
        }

        $config = $variants[$active] ?? $variants[$default] ?? [];

        return [
            'key' => $active,
            'hero_partial' => $config['hero_partial'] ?? 'site.home._hero-a',
            'products_first' => (bool) ($config['products_first'] ?? false),
        ];
    }
}

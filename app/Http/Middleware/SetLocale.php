<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** @param \Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $countries = app(\App\Services\CountrySettingsService::class);
        if ($countries->tanzaniaOnlyMode()) {
            $sessionCountry = strtoupper((string) $request->session()->get('country', 'TZ'));
            if ($sessionCountry !== 'TZ') {
                $request->session()->put('country', 'TZ');
            }
        }

        $user = $request->user('admin') ?? $request->user();
        $isAdminSurface = $request->is('admin', 'admin/*', 'staff', 'staff/*');

        if ($isAdminSurface) {
            $locale = $request->session()->get('admin_locale');
            $preferred = data_get($user?->preferences, 'admin_locale');
            if ((! is_string($locale) || $locale === '') && is_string($preferred) && in_array($preferred, ['en', 'sw'], true)) {
                $locale = $preferred;
                $request->session()->put('admin_locale', $locale);
            }
            // Admin/staff default to English so public-site Kiswahili cannot mix the console.
            if (! is_string($locale) || $locale === '') {
                $locale = 'en';
            }
        } else {
            $locale = $request->session()->get('locale');

            $preferred = data_get($user?->preferences, 'preferred_locale')
                ?? data_get($user?->preferences, 'locale');
            if ((! is_string($locale) || $locale === '') && is_string($preferred) && in_array($preferred, ['en', 'sw'], true)) {
                $locale = $preferred;
                $request->session()->put('locale', $locale);
            }

            // Tanzania-first product: Kiswahili is the default until the visitor picks English.
            if (! is_string($locale) || $locale === '') {
                $country = strtoupper((string) $request->session()->get('country', 'TZ'));
                $locale = $country === 'TZ'
                    ? 'sw'
                    : (string) config('app.locale', 'sw');
            }
        }

        if (! in_array($locale, ['en', 'sw'], true)) {
            $locale = $isAdminSurface ? 'en' : 'sw';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

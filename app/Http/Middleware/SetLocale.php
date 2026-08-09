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
        $locale = $request->session()->get('locale');

        // Tanzania-first product: Kiswahili is the default until the visitor picks English.
        if (! is_string($locale) || $locale === '') {
            $country = strtoupper((string) $request->session()->get('country', 'TZ'));
            $locale = $country === 'TZ'
                ? 'sw'
                : (string) config('app.locale', 'sw');
        }

        if (! in_array($locale, ['en', 'sw'], true)) {
            $locale = 'sw';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}

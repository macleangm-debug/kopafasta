<?php

return [
    'app_name'      => env('BRAND_APP_NAME', 'kopafasta'),
    'legal_name'    => env('BRAND_LEGAL_NAME', 'Kopafasta Microfinance Ltd'),
    'platform_name' => env('BRAND_PLATFORM_NAME', 'kopafasta'),
    'tagline'       => env('BRAND_TAGLINE', 'Capital that moves at your pace'),
    'logo_letter'   => env('BRAND_LOGO_LETTER', 'K'),
    // Prefer composing icon + CSS wordmark via <x-site.brand-mark>.
    // logo_url kept for single-file PDF/email fallbacks (SVG lockup with black text).
    'logo_url'       => env('BRAND_LOGO_URL', '/images/brand/kopafasta-logo.svg'),
    'logo_url_light' => env('BRAND_LOGO_URL_LIGHT', '/images/brand/kopafasta-logo-light.svg'),
    // Official icon only (transparent PNG) — favicons, cards, colored headers.
    'logo_mark_url'  => env('BRAND_LOGO_MARK_URL', '/images/brand/kopafasta-mark.png'),
    'support_email' => env('BRAND_SUPPORT_EMAIL', 'hello@kopafasta.com'),
    'support_phone' => env('BRAND_SUPPORT_PHONE', '+255 700 000 000'),
    'support_whatsapp' => env('BRAND_SUPPORT_WHATSAPP', '255700000000'),
];

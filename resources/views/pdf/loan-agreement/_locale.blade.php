@php
    $locale = $snapshot['locale'] ?? app()->getLocale();
    $locale = in_array($locale, ['en', 'sw'], true)
        ? $locale
        : (str_starts_with((string) $locale, 'sw') ? 'sw' : 'en');
    $isSw = $locale === 'sw';
    $t = static fn (string $en, string $sw): string => $isSw ? $sw : $en;
@endphp

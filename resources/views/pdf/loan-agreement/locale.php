<?php

$locale = $snapshot['locale'] ?? app()->getLocale();
$locale = in_array($locale, ['en', 'sw'], true)
    ? $locale
    : (str_starts_with((string) $locale, 'sw') ? 'sw' : 'en');
$isSw = $locale === 'sw';
$t = static fn (string $en, string $sw): string => pdf_text($isSw ? $sw : $en);
$roleLabel = static function (?string $role) use ($t): string {
    return match ($role) {
        'leader' => $t('Group leader', 'Kiongozi wa kikundi'),
        'guarantor' => $t('Guarantor', 'Mdhamini'),
        default => $t('Group member', 'Mwanachama'),
    };
};

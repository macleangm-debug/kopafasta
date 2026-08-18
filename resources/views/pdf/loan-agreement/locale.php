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

$matchKeyedLabel = static function (?string $value, array $keys, string $langFile) use ($locale): ?string {
    if (! filled($value)) {
        return null;
    }
    $raw = trim((string) $value);
    if (isset($keys[$raw])) {
        $translated = __($langFile.'.'.$raw, [], $locale);

        return $translated !== $langFile.'.'.$raw ? $translated : null;
    }
    foreach ($keys as $key => $english) {
        $en = __($langFile.'.'.$key, [], 'en');
        $sw = __($langFile.'.'.$key, [], 'sw');
        if (strcasecmp((string) $english, $raw) === 0
            || strcasecmp($en, $raw) === 0
            || strcasecmp($sw, $raw) === 0
            || strcasecmp(str_replace('_', ' ', $key), $raw) === 0) {
            $translated = __($langFile.'.'.$key, [], $locale);

            return $translated !== $langFile.'.'.$key ? $translated : $english;
        }
    }

    return null;
};

$activityLabel = static function (?string $value) use ($matchKeyedLabel): string {
    return $matchKeyedLabel($value, config('activity_profiles.types', []), 'activity.types')
        ?: (filled($value) ? (string) $value : '—');
};

$purposeLabel = static function (?string $purpose, ?string $purposeOther = null) use ($locale, $matchKeyedLabel): string {
    $key = normalize_loan_purpose_key($purpose);
    $label = $matchKeyedLabel($key ?? $purpose, config('loan_purposes', []), 'activity.loan_purposes');
    if (! $label) {
        $label = format_loan_purpose_display($purpose, $purposeOther);
    }
    if (filled($purposeOther) && ($key === 'other' || is_loan_purpose_other($purpose))) {
        return trim($label.': '.$purposeOther);
    }

    return $label ?: (filled($purpose) ? (string) $purpose : '—');
};

$jurisdictionLabel = static function (?string $value) use ($t): string {
    $raw = trim((string) $value);
    if ($raw === ''
        || strcasecmp($raw, 'United Republic of Tanzania') === 0
        || strcasecmp($raw, 'Jamhuri ya Muungano wa Tanzania') === 0) {
        return $t('United Republic of Tanzania', 'Jamhuri ya Muungano wa Tanzania');
    }

    return $raw;
};

$signStatusLabel = static function (?string $status) use ($t): string {
    return match ($status) {
        'signed' => $t('Signed', 'Imesainiwa'),
        'declined' => $t('Declined', 'Imekataliwa'),
        default => $t('Pending', 'Inasubiri'),
    };
};

$relationshipLabel = static function (?string $value) use ($t): string {
    if (! filled($value)) {
        return '—';
    }

    return match (strtolower(str_replace([' ', '-'], '_', (string) $value))) {
        'spouse', 'husband', 'wife' => $t('Spouse', 'Mwenza'),
        'parent', 'father', 'mother' => $t('Parent', 'Mzazi'),
        'sibling', 'brother', 'sister' => $t('Sibling', 'Ndugu'),
        'child', 'son', 'daughter' => $t('Child', 'Mtoto'),
        'relative', 'family' => $t('Relative', 'Ndugu'),
        'friend' => $t('Friend', 'Rafiki'),
        'colleague', 'coworker' => $t('Colleague', 'Mwenzako kazini'),
        'member' => $t('Member', 'Mwanachama'),
        'business_partner', 'partner' => $t('Business partner', 'Mshirika wa biashara'),
        default => (string) $value,
    };
};

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ ($snapshot['locale'] ?? app()->getLocale()) === 'sw' ? 'Mkataba wa Mkopo na Masharti ya Huduma ya Mkopo' : 'Loan Agreement and Facility Terms' }} — {{ $agreement->reference }}</title>
@include('pdf.loan-agreement._styles')
</head>
<body>
@php
    $locale = $snapshot['locale'] ?? app()->getLocale();
    $locale = in_array($locale, ['en', 'sw'], true) ? $locale : (str_starts_with((string) $locale, 'sw') ? 'sw' : 'en');
    $isSw = $locale === 'sw';
    $t = static fn (string $en, string $sw): string => $isSw ? $sw : $en;
@endphp
@include('pdf._brand_band', [
    'bandTitle' => $snapshot['company_legal_name'] ?? brand('legal_name'),
    'bandTag' => $t('LOAN AGREEMENT AND FACILITY TERMS', 'MKATABA WA MKOPO NA MASHARTI YA HUDUMA YA MKOPO'),
    'bandMeta' => ($t('Ref', 'Rejea').': '.$agreement->reference).'<br>'.($t('App', 'Ombi').': '.($snapshot['application_number'] ?? '—')).'<br>'.now()->format('d M Y'),
])
<div class="wrap">
    <p class="muted">{{ $snapshot['company_address'] ?? '' }} · {{ $t('Licence', 'Leseni') }} {{ $snapshot['licence_number'] ?? '—' }} · {{ $snapshot['jurisdiction'] ?? 'United Republic of Tanzania' }}</p>
    @include('pdf.loan-agreement.body', ['includeScheduleAnnex' => false])
</div>
</body>
</html>

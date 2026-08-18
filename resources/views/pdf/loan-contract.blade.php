<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="UTF-8">
<title>{{ pdf_text(($snapshot['locale'] ?? app()->getLocale()) === 'sw' ? 'Mkataba wa Mkopo na Masharti ya Huduma ya Mkopo' : 'Loan Agreement and Facility Terms') }} - {{ $agreement->reference }}</title>
@include('pdf.loan-agreement._styles')
</head>
<body>
@php require resource_path('views/pdf/loan-agreement/locale.php'); @endphp
@include('pdf._brand_band', [
    'bandTitle' => $snapshot['company_legal_name'] ?? brand('legal_name'),
    'bandTag' => $t('Loan Agreement and Facility Terms', 'Mkataba wa Mkopo na Masharti ya Huduma ya Mkopo'),
    'bandMeta' => e($t('Ref', 'Rejea').': '.$agreement->reference).'<br>'.e($t('App', 'Ombi').': '.($snapshot['application_number'] ?? '-')).'<br>'.e(now()->format('d M Y')),
])
<div class="wrap">
    <p>{{ pdf_text($snapshot['company_address'] ?? '') }} - {{ $t('Licence', 'Leseni') }} {{ pdf_text($snapshot['licence_number'] ?? '-') }} - {{ pdf_text($jurisdictionLabel($snapshot['jurisdiction'] ?? null)) }}</p>
    @include('pdf.loan-agreement.body', ['includeScheduleAnnex' => false])
</div>
</body>
</html>

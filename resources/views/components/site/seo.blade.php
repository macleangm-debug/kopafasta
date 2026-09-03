@props([
    'document' => null,
])
@php
    /** @var \App\Support\SeoDocument $document */
@endphp
<title>{{ $document->title }}</title>
<meta name="description" content="{{ $document->description }}">
<meta name="robots" content="{{ $document->robots }}">
<link rel="canonical" href="{{ $document->canonical }}">
<meta property="og:type" content="{{ $document->ogType }}">
<meta property="og:site_name" content="{{ brand_name() }}">
<meta property="og:title" content="{{ $document->ogTitle }}">
<meta property="og:description" content="{{ $document->ogDescription }}">
<meta property="og:url" content="{{ $document->canonical }}">
<meta property="og:locale" content="{{ $document->locale === 'sw' ? 'sw_TZ' : 'en_TZ' }}">
@if ($document->ogImage)
    <meta property="og:image" content="{{ $document->ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ $document->ogImage }}">
@else
    <meta name="twitter:card" content="summary">
@endif
<meta name="twitter:title" content="{{ $document->ogTitle }}">
<meta name="twitter:description" content="{{ $document->ogDescription }}">
@if ($document->googleSiteVerification)
    <meta name="google-site-verification" content="{{ $document->googleSiteVerification }}">
@endif
@if ($document->bingSiteVerification)
    <meta name="msvalidate.01" content="{{ $document->bingSiteVerification }}">
@endif
@foreach ($document->jsonLd as $block)
    <script type="application/ld+json">{!! json_encode($block, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach

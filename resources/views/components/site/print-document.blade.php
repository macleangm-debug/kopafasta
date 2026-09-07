@props([
    'title' => null,
    'footerLeft' => null,
    'footerRight' => null,
])

@php
    $pageTitle = $title ?? brand_title('Report');
    $seoDocument = app(\App\Services\SeoService::class)->privateDocument(request(), $pageTitle);
    $footerLine = trim(implode(' · ', array_filter([
        $footerLeft,
        $footerRight,
    ], fn ($part) => filled($part))));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $seoDocument->title ?: $pageTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @page {
            size: A4;
            margin: 12mm 12mm 22mm;
        }
        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .kf-print-chrome { display: none !important; }
            /* Fixed application footer on every printed page (Chrome headers/footers OFF in UAT). */
            .kf-print-running-footer {
                position: fixed !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                display: flex !important;
                align-items: flex-start;
                gap: 8px;
                padding: 3mm 0 0;
                border-top: 0.4pt solid #d1d5db;
                background: #fff !important;
                font-size: 7.5pt;
                line-height: 1.35;
                color: #4b5563;
                z-index: 50;
                box-sizing: border-box;
            }
            .kf-print-running-footer p {
                white-space: normal !important;
                overflow: visible !important;
                text-overflow: clip !important;
                word-break: break-word;
            }
            .kf-print-root {
                padding-bottom: 18mm !important;
            }
            .kf-print-app-footer { display: none !important; }
        }
        @media screen {
            body { background: #f3f4f6; }
            .kf-print-running-footer { display: none; }
        }
    </style>
</head>
<body class="kf-print-document antialiased text-gray-900">
    <div class="kf-print-chrome px-4 py-3 flex items-center justify-between gap-3 bg-white border-b border-gray-200 sticky top-0 z-10">
        <button type="button"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand rounded-xl ring-1 ring-brand/20 px-3 py-2 hover:bg-brand/5"
                onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = @js(route('site.borrower.plus.reports')); }">
            ← {{ __('plus.learn.prev') }}
        </button>
        <button type="button" class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold" onclick="window.print()">
            {{ __('plus.reports.print') }}
        </button>
    </div>

    <main class="kf-print-root mx-auto my-4 sm:my-8 max-w-[210mm] bg-white shadow-sm ring-1 ring-brand/10 print:shadow-none print:ring-0 print:my-0 print:max-w-none">
        {{ $slot }}
    </main>

    {{-- Matches accepted in-app footer: Plus branding left + full uncut details. --}}
    <div class="kf-print-running-footer" aria-hidden="true">
        <div class="shrink-0 inline-flex items-center gap-1.5 pr-2">
            <img src="{{ asset(ltrim((string) (brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png'), '/')) }}"
                 alt="" class="h-3.5 w-auto object-contain">
            <span class="font-bold tracking-tight text-[8pt] text-brand whitespace-nowrap">{{ $footerLeft ?: __('plus.reports.print_plus_label') }}</span>
        </div>
        <p class="min-w-0 flex-1 leading-snug">{{ $footerRight ?: $footerLine }}</p>
    </div>
</body>
</html>

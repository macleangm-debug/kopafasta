@props([
    'title' => null,
    'footerLeft' => null,
    'footerRight' => null,
])

@php
    $pageTitle = $title ?? brand_title('Report');
    $seoDocument = app(\App\Services\SeoService::class)->privateDocument(request(), $pageTitle);
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
            margin: 14mm 12mm 20mm;
            @bottom-center {
                content: counter(page);
            }
        }
        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .kf-print-chrome { display: none !important; }
            .kf-print-running-footer {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                padding: 4mm 0 2mm;
                font-size: 8pt;
                color: #4b5563;
                border-top: 0.4pt solid #d1d5db;
                background: #fff;
            }
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

    <div class="kf-print-running-footer" aria-hidden="true">
        <div class="flex items-center justify-between gap-3 text-[8pt] leading-snug">
            <div class="inline-flex items-center gap-2 min-w-0">
                <img src="{{ asset(ltrim((string) (brand('logo_mark_url') ?: brand('logo_url') ?: 'images/brand/kopafasta-mark.png'), '/')) }}"
                     alt="" class="h-4 w-auto object-contain shrink-0">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-700">{{ $footerLeft ?? 'Kopafasta Plus Report' }}</p>
                    <p>{{ brand('legal_name', 'Kopafasta Microfinance Limited') }}</p>
                </div>
            </div>
            <div class="text-right shrink-0">
                <p>{{ $footerRight }}</p>
                <p>{{ \App\Models\Setting::get('company.website') ?: \App\Models\Setting::get('company.app_base_url') ?: config('app.url') }}</p>
            </div>
        </div>
    </div>
</body>
</html>

@props([
    'title' => null,
    'footerLeft' => null,
    'footerRight' => null,
])

@php
    $pageTitle = $title ?? brand_title('Report');
    $seoDocument = app(\App\Services\SeoService::class)->privateDocument(request(), $pageTitle);
    $footerMeta = __('plus.reports.footer_confidential');
    $logoPath = public_path(ltrim((string) (brand('logo_mark_url') ?: 'images/brand/kopafasta-mark.png'), '/'));
    if (! is_file($logoPath)) {
        $logoPath = public_path('images/brand/kopafasta-mark.png');
    }
    $logoDataUri = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
        : asset('images/brand/kopafasta-mark.png');
    $brandWordmark = brand_name();
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
            margin: 12mm 12mm 18mm;
        }
        @media print {
            html, body {
                background: #fff !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .kf-print-chrome { display: none !important; }
            a[href]::after { content: none !important; }
            /* Fixed application footer on every printed page (Chrome headers/footers OFF in UAT). */
            .kf-print-running-footer {
                position: fixed !important;
                left: 12mm !important;
                right: 12mm !important;
                bottom: 6mm !important;
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                padding: 2.5mm 0 0;
                border-top: 0.4pt solid #d1d5db;
                background: #fff !important;
                font-size: 8pt;
                line-height: 1.3;
                color: #4b5563;
                z-index: 50;
                box-sizing: border-box;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .kf-print-running-footer .kf-print-lockup {
                display: inline-flex !important;
                align-items: center;
                gap: 6px;
            }
            .kf-print-running-footer img {
                display: inline-block !important;
                height: 14px !important;
                width: auto !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .kf-print-running-footer .kf-print-wordmark {
                font-weight: 700;
                font-size: 9pt;
                letter-spacing: -0.02em;
                color: #111827 !important;
                line-height: 1;
            }
            .kf-print-running-footer p {
                margin: 0;
                white-space: nowrap !important;
                overflow: visible !important;
                text-overflow: clip !important;
            }
            .kf-print-root {
                padding-bottom: 16mm !important;
            }
            .kf-print-app-footer { display: none !important; }
        }
        @media screen {
            body { background: #f3f4f6; }
            .kf-print-running-footer {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                max-width: 210mm;
                margin: 0 auto 1.5rem;
                padding: 0.75rem 1rem 0;
                border-top: 1px solid #d1d5db;
                font-size: 12px;
                line-height: 1.35;
                color: #4b5563;
                background: #fff;
            }
            .kf-print-running-footer .kf-print-lockup {
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            .kf-print-running-footer img {
                height: 18px;
                width: auto;
            }
            .kf-print-running-footer .kf-print-wordmark {
                font-weight: 700;
                font-size: 14px;
                letter-spacing: -0.02em;
                color: #111827;
                line-height: 1;
            }
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

    {{-- Full lockup left · Confidential / Faragha right. No URL or Plus branding. --}}
    <footer class="kf-print-running-footer" aria-label="{{ $brandWordmark }}">
        <div class="kf-print-lockup shrink-0">
            <img src="{{ $logoDataUri }}" alt="" width="48" height="48" class="object-contain" aria-hidden="true">
            <span class="kf-print-wordmark">{{ $brandWordmark }}</span>
        </div>
        <p class="min-w-0 text-right leading-snug">{{ $footerMeta }}</p>
    </footer>
</body>
</html>

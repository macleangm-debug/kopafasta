@php
    $money = $report['money'];
    $biz = $report['business'];
    $left = (float) ($money['left'] ?? ((float) $money['in'] - (float) $money['out']));
    $months = $report['months'] ?? [];
    $currentMonth = $report['month'] ?? now()->format('Y-m');
    $monthIndex = collect($months)->search(fn ($choice) => ($choice['value'] ?? '') === $currentMonth);
    $newer = is_int($monthIndex) && $monthIndex > 0 ? $months[$monthIndex - 1] : null;
    $older = is_int($monthIndex) && isset($months[$monthIndex + 1]) ? $months[$monthIndex + 1] : null;
    $review = $report['observations'] ?? [];
    if ($review === []) {
        $review = $report['noticed'] ?? [];
    }
    $businessContext = $report['business_context'] ?? __('plus.business.all_businesses');
    $footerLine = __('plus.reports.footer_confidential');
    $logoPath = public_path(ltrim((string) (brand('logo_mark_url') ?: 'images/brand/kopafasta-mark.png'), '/'));
    if (! is_file($logoPath)) {
        $logoPath = public_path('images/brand/kopafasta-mark.png');
    }
    $logoDataUri = is_file($logoPath)
        ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
        : asset('images/brand/kopafasta-mark.png');
    $brandWordmark = brand_name();
@endphp

{{-- Preview stays inside borrower account shell. Print opens a blob document so Chrome cannot inject the reports route URL. --}}
<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="plus">
    <div class="space-y-5">
        <x-site.plus-hero
            kicker="Kopafasta Plus"
            :title="__('plus.home.reports')"
            :body="__('plus.reports.hero_body')"
            class="print:hidden"
        />

        @if ($report['thin'] ?? false)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-100 p-4 print:hidden">
                <p class="font-semibold text-gray-900">{{ __('plus.reports.thin_title') }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.thin_body', ['days' => $report['days_recorded'] ?? 0]) }}</p>
                <a href="{{ route('site.borrower.plus.money') }}" class="mt-3 inline-flex text-sm font-semibold text-brand">{{ __('plus.reports.thin_cta') }}</a>
            </div>
        @endif

        <div class="kf-print-root">
            @include('site.plus._report_sheet', [
                'report' => $report,
                'money' => $money,
                'biz' => $biz,
                'left' => $left,
                'months' => $months,
                'currentMonth' => $currentMonth,
                'newer' => $newer,
                'older' => $older,
                'review' => $review,
                'businessContext' => $businessContext,
                'print' => false,
            ])
        </div>

        <div class="print:hidden flex flex-col sm:flex-row sm:items-center gap-3">
            <button type="button"
                    data-kf-plus-print
                    class="inline-flex justify-center rounded-xl bg-brand text-white px-5 py-3 font-semibold">
                {{ __('plus.reports.print') }}
            </button>
            <p class="text-xs text-gray-500">{{ __('plus.reports.print_goes') }}</p>
        </div>
    </div>

    {{-- Application print footer only. No URL. Printed from blob document (not this page location). --}}
    <footer class="kf-print-running-footer" aria-label="{{ $brandWordmark }}">
        <div class="kf-print-lockup shrink-0">
            <img src="{{ $logoDataUri }}" alt="" width="48" height="48" class="object-contain" aria-hidden="true">
            <span class="kf-print-wordmark">{{ $brandWordmark }}</span>
        </div>
        <p class="min-w-0 flex-1 text-right leading-snug">{{ $footerLine }}</p>
    </footer>

    <script>
        (function () {
            function kfPlusPrintStyles() {
                return Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
                    .map(function (node) { return node.outerHTML; })
                    .join('\n');
            }

            function kfPrintPlusReport() {
                var root = document.querySelector('.kf-print-root');
                var footer = document.querySelector('.kf-print-running-footer');
                if (!root) {
                    return;
                }

                // Source of prior URL: Chrome header/footer of this page location.
                // Print a blob document so the reports route is not the printed document URL.
                var html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title></title>'
                    + kfPlusPrintStyles()
                    + '<style>'
                    + '@page{size:A4;margin:12mm 12mm 22mm;}'
                    + 'html,body{background:#fff!important;margin:0!important;padding:0!important;}'
                    + 'a[href]::after{content:none!important;}'
                    + '.kf-print-running-footer{position:fixed!important;left:12mm!important;right:12mm!important;bottom:6mm!important;display:flex!important;align-items:center;justify-content:space-between;gap:8px;background:#fff!important;z-index:50;padding-top:2.5mm;border-top:0.4pt solid #d1d5db;font-size:8pt;line-height:1.3;color:#4b5563;}'
                    + '.kf-print-running-footer .kf-print-lockup{display:inline-flex!important;align-items:center;gap:6px;}'
                    + '.kf-print-running-footer img{display:inline-block!important;height:14px!important;width:auto!important;}'
                    + '.kf-print-running-footer .kf-print-wordmark{font-weight:700;font-size:9pt;letter-spacing:-0.02em;color:#111827!important;}'
                    + '.kf-print-running-footer p{margin:0;white-space:nowrap!important;}'
                    + '.kf-print-root{padding-bottom:16mm!important;}'
                    + '.print\\:hidden,.plus-nav,[data-plus-nav]{display:none!important;}'
                    + '</style></head><body>'
                    + root.outerHTML
                    + (footer ? footer.outerHTML : '')
                    + '</body></html>';

                var blob = new Blob([html], { type: 'text/html' });
                var url = URL.createObjectURL(blob);
                var win = window.open(url, '_blank', 'noopener,noreferrer,width=900,height=1200');
                if (!win) {
                    URL.revokeObjectURL(url);
                    return;
                }
                var printed = false;
                var runPrint = function () {
                    if (printed) {
                        return;
                    }
                    printed = true;
                    try {
                        win.focus();
                        win.print();
                    } finally {
                        setTimeout(function () {
                            try { win.close(); } catch (e) {}
                            URL.revokeObjectURL(url);
                        }, 800);
                    }
                };
                win.onload = runPrint;
                setTimeout(runPrint, 400);
            }

            document.querySelectorAll('[data-kf-plus-print]').forEach(function (btn) {
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    kfPrintPlusReport();
                });
            });
        })();
    </script>
</x-site.borrower-layout>

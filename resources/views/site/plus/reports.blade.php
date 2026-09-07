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
    $shareText = __('plus.reports.share_text');
    $shareTitle = $brandWordmark.' · '.__('plus.home.reports');
    $pdfUrl = route('site.borrower.plus.reports.pdf', array_filter([
        'month' => $currentMonth !== now()->format('Y-m') ? $currentMonth : null,
    ]));
    $enabledSocial = collect(social_links())->pluck('platform')->all();
    $showFacebook = in_array('facebook', $enabledSocial, true);
@endphp

{{-- Accepted shell preview + blob print (no app URL). Share uses Kopafasta sheet + generated PDF when supported. --}}
<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="plus">
    <div class="space-y-5"
         x-data="plusReportActions(@js([
             'shareText' => $shareText,
             'shareTitle' => $shareTitle,
             'pdfUrl' => $pdfUrl,
             'pdfName' => 'kopafasta-plus-report-'.$currentMonth.'.pdf',
             'copyPrompt' => __('plus.reports.share_copy_prompt'),
         ]))">
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
            <button type="button"
                    @click="openShare()"
                    class="inline-flex justify-center rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand/5 px-5 py-3 font-semibold">
                {{ __('plus.reports.share') }}
            </button>
            <p class="text-xs text-gray-500">{{ __('plus.reports.print_goes') }}</p>
        </div>

        <x-site.kopafasta-share-sheet
            :title="__('plus.reports.share')"
            :hint="__('plus.reports.share_hint')"
            :show-facebook="$showFacebook"
            :show-download="true"
            open="shareOpen"
            :whatsapp-label="__('plus.reports.share_whatsapp')"
            :facebook-label="__('plus.reports.share_facebook')"
            :messages-label="__('plus.reports.share_messages')"
            :email-label="__('plus.reports.share_email')"
            :copy-label="__('plus.reports.share_copy')"
            :copied-label="__('plus.reports.share_copied')"
            :more-label="__('plus.reports.share_more')"
            :download-label="__('plus.reports.share_download')"
        />
    </div>

    {{-- Application print footer only. Logo left · Confidential/Faragha right. No URL. --}}
    <footer class="kf-print-running-footer" aria-label="{{ $brandWordmark }}">
        <div class="kf-print-lockup shrink-0">
            <img src="{{ $logoDataUri }}" alt="" width="48" height="48" class="object-contain" aria-hidden="true">
            <span class="kf-print-wordmark">{{ $brandWordmark }}</span>
        </div>
        <p class="min-w-0 flex-1 text-right leading-snug">{{ $footerLine }}</p>
    </footer>

    <script>
        function plusReportActions(cfg) {
            cfg = cfg || {};
            return {
                shareOpen: false,
                shareText: cfg.shareText || '',
                shareTitle: cfg.shareTitle || '',
                pdfUrl: cfg.pdfUrl || '',
                pdfName: cfg.pdfName || 'kopafasta-plus-report.pdf',
                copyPrompt: cfg.copyPrompt || 'Copy this link',
                copied: false,
                shareFile: null,
                canNativeShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
                openShare() {
                    this.shareOpen = true;
                    this.preparePdf();
                },
                async preparePdf() {
                    if (this.shareFile || ! this.pdfUrl) return;
                    try {
                        const res = await fetch(this.pdfUrl, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/pdf' },
                            credentials: 'same-origin',
                        });
                        if (! res.ok) return;
                        const blob = await res.blob();
                        this.shareFile = new File([blob], this.pdfName, { type: 'application/pdf' });
                    } catch (e) {
                        this.shareFile = null;
                    }
                },
                async withFileOrText(channelFallback) {
                    await this.preparePdf();
                    if (this.shareFile && navigator.canShare && navigator.canShare({ files: [this.shareFile] })) {
                        try {
                            await navigator.share({
                                files: [this.shareFile],
                                title: this.shareTitle,
                                text: this.shareText,
                            });
                            this.shareOpen = false;
                            return;
                        } catch (e) {
                            if (e && e.name === 'AbortError') return;
                        }
                    }
                    channelFallback();
                },
                shareWhatsApp() {
                    this.withFileOrText(() => {
                        window.open('https://wa.me/?text=' + encodeURIComponent(this.shareText), '_blank', 'noopener');
                    });
                },
                shareFacebook() {
                    // Private report has no public URL — prefer PDF share, else Save PDF.
                    this.withFileOrText(() => {
                        this.downloadShare();
                    });
                },
                shareMessages() {
                    this.withFileOrText(() => {
                        window.location.href = 'sms:?&body=' + encodeURIComponent(this.shareText);
                    });
                },
                shareEmail() {
                    this.withFileOrText(() => {
                        window.location.href = 'mailto:?subject=' + encodeURIComponent(this.shareTitle)
                            + '&body=' + encodeURIComponent(this.shareText);
                    });
                },
                async downloadShare() {
                    await this.preparePdf();
                    if (! this.shareFile && this.pdfUrl) {
                        window.location.href = this.pdfUrl;
                        return;
                    }
                    if (! this.shareFile) return;
                    const href = URL.createObjectURL(this.shareFile);
                    const a = document.createElement('a');
                    a.href = href;
                    a.download = this.pdfName;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(href), 1000);
                },
                async copyShare() {
                    const text = this.shareText;
                    try {
                        await navigator.clipboard.writeText(text);
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 2200);
                    } catch (e) {
                        window.prompt(this.copyPrompt, text);
                    }
                },
                async shareMore() {
                    if (! this.canNativeShare) return;
                    await this.preparePdf();
                    try {
                        const payload = { title: this.shareTitle, text: this.shareText };
                        if (this.shareFile && navigator.canShare && navigator.canShare({ files: [this.shareFile] })) {
                            payload.files = [this.shareFile];
                        }
                        await navigator.share(payload);
                        this.shareOpen = false;
                    } catch (e) {
                        if (e && e.name === 'AbortError') return;
                    }
                },
            };
        }

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

                // Blob document so Chrome cannot inject the reports-route URL as the document URL.
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
                    + '.print\\:hidden,.plus-nav,[data-plus-nav],[x-cloak]{display:none!important;}'
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

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
    $shareUrl = url()->current();
    $shareText = __('plus.reports.share_text', ['brand' => $brandWordmark]);
    $shareTitle = $brandWordmark.' · '.__('plus.home.reports');
    $enabledSocial = collect(social_links())->pluck('platform')->all();
    $showFacebook = in_array('facebook', $enabledSocial, true);
@endphp

{{-- Preview stays in borrower shell. Print dialog opens directly via hidden iframe (no reports-route URL). --}}
<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="plus">
    <div class="space-y-5"
         x-data="plusReportActions(@js([
             'shareUrl' => $shareUrl,
             'shareText' => $shareText,
             'shareTitle' => $shareTitle,
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
                    @click="printReport()"
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

        {{-- Mobile: single bottom sheet · Desktop: compact modal. Native share only via More. --}}
        <x-site.action-panel :title="__('plus.reports.share')" open="shareOpen" size="md">
            <p class="text-sm text-gray-600 mb-4">{{ __('plus.reports.share_hint') }}</p>
            <div class="grid grid-cols-1 gap-2.5">
                <a :href="'https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + shareUrl)"
                   target="_blank" rel="noopener"
                   class="flex items-center gap-3 rounded-2xl bg-brand-gold/15 ring-1 ring-brand-gold/30 px-4 py-3.5 hover:bg-brand-gold/25 transition">
                    <span class="size-11 rounded-xl bg-[#25D366] text-white grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11 11 0 0 0 2.1 17.7L1 23l5.4-1.4A11 11 0 1 0 20.5 3.5zm-8.6 17a9.1 9.1 0 0 1-4.6-1.3l-.3-.2-3.2.8.9-3.1-.2-.3a9.1 9.1 0 1 1 7.4 4.1zm5-6.8c-.3-.1-1.6-.8-1.8-.9-.2-.1-.4-.1-.6.1-.2.3-.7.9-.8 1-.1.2-.3.2-.6.1-.3-.2-1.2-.4-2.2-1.4-.8-.7-1.4-1.6-1.5-1.9-.2-.3 0-.4.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.6-1.5-.8-2-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.3.3-.9.9-.9 2.1s.9 2.4 1 2.6c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.2.8 3 .7.5-.1 1.6-.6 1.8-1.3.2-.6.2-1.2.1-1.3-.1-.1-.3-.2-.6-.3z"/></svg>
                    </span>
                    <span class="text-base font-bold text-gray-900">{{ __('plus.reports.share_whatsapp') }}</span>
                </a>

                @if ($showFacebook)
                    <a :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl)"
                       target="_blank" rel="noopener"
                       class="flex items-center gap-3 rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3.5 hover:bg-brand/10 transition">
                        <span class="size-11 rounded-xl bg-[#1877F2] text-white grid place-items-center shrink-0" aria-hidden="true">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>
                        </span>
                        <span class="text-base font-bold text-gray-900">{{ __('plus.reports.share_facebook') }}</span>
                    </a>
                @endif

                <a :href="'sms:?&body=' + encodeURIComponent(shareText + ' ' + shareUrl)"
                   class="flex items-center gap-3 rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3.5 hover:bg-brand/10 transition lg:hidden">
                    <span class="size-11 rounded-xl bg-emerald-600 text-white grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
                    </span>
                    <span class="text-base font-bold text-gray-900">{{ __('plus.reports.share_messages') }}</span>
                </a>

                <a :href="'mailto:?subject=' + encodeURIComponent(shareTitle) + '&body=' + encodeURIComponent(shareText + '\n\n' + shareUrl)"
                   class="flex items-center gap-3 rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3.5 hover:bg-brand/10 transition">
                    <span class="size-11 rounded-xl bg-brand text-white grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
                    </span>
                    <span class="text-base font-bold text-gray-900">{{ __('plus.reports.share_email') }}</span>
                </a>

                <button type="button" @click="copyLink()"
                        class="flex items-center gap-3 rounded-2xl bg-white ring-1 ring-brand/20 px-4 py-3.5 hover:bg-brand/5 transition text-left w-full">
                    <span class="size-11 rounded-xl bg-gray-900 text-white grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                    </span>
                    <span class="text-base font-bold text-gray-900">
                        <span x-show="!copied">{{ __('plus.reports.share_copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('plus.reports.share_copied') }}</span>
                    </span>
                </button>

                <button type="button" x-show="canNativeShare" x-cloak @click="shareMore()"
                        class="flex items-center gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 hover:bg-gray-50 transition text-left w-full">
                    <span class="size-11 rounded-xl bg-gray-100 text-brand grid place-items-center shrink-0" aria-hidden="true">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="2"/><circle cx="6" cy="12" r="2"/><circle cx="18" cy="19" r="2"/><path d="M8.6 13.5 15.4 17M15.4 7 8.6 10.5"/></svg>
                    </span>
                    <span class="text-base font-bold text-gray-900">{{ __('plus.reports.share_more') }}</span>
                </button>
            </div>
        </x-site.action-panel>
    </div>

    {{-- Application print footer only. Chrome Headers/footers OFF in UAT. --}}
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
                shareUrl: cfg.shareUrl || '',
                shareText: cfg.shareText || '',
                shareTitle: cfg.shareTitle || '',
                copyPrompt: cfg.copyPrompt || 'Copy this link',
                copied: false,
                canNativeShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
                openShare() {
                    this.shareOpen = true;
                },
                async copyLink() {
                    try {
                        await navigator.clipboard.writeText(this.shareUrl);
                        this.copied = true;
                        setTimeout(() => { this.copied = false; }, 2200);
                    } catch (e) {
                        window.prompt(this.copyPrompt, this.shareUrl);
                    }
                },
                async shareMore() {
                    if (! this.canNativeShare) return;
                    try {
                        await navigator.share({
                            title: this.shareTitle,
                            text: this.shareText,
                            url: this.shareUrl,
                        });
                        this.shareOpen = false;
                    } catch (e) {
                        if (e && e.name === 'AbortError') return;
                    }
                },
                printReport() {
                    const root = document.querySelector('.kf-print-root');
                    const footer = document.querySelector('.kf-print-running-footer');
                    if (! root) {
                        window.print();
                        return;
                    }
                    const styles = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'))
                        .map((node) => node.outerHTML)
                        .join('\n');
                    const html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title></title>'
                        + styles
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
                        + '.print\\:hidden,[x-cloak],.plus-nav,[data-plus-nav]{display:none!important;}'
                        + '</style></head><body>'
                        + root.outerHTML
                        + (footer ? footer.outerHTML : '')
                        + '</body></html>';

                    const iframe = document.createElement('iframe');
                    iframe.setAttribute('title', 'print');
                    iframe.setAttribute('aria-hidden', 'true');
                    iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;opacity:0;pointer-events:none;';
                    document.body.appendChild(iframe);
                    const doc = iframe.contentDocument || iframe.contentWindow.document;
                    doc.open();
                    doc.write(html);
                    doc.close();
                    const run = () => {
                        try {
                            iframe.contentWindow.focus();
                            iframe.contentWindow.print();
                        } finally {
                            setTimeout(() => iframe.remove(), 1000);
                        }
                    };
                    setTimeout(run, 250);
                },
            };
        }
    </script>
</x-site.borrower-layout>

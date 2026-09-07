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
@endphp

{{-- Preview stays inside borrower account shell. Print opens the browser print dialog directly. --}}
<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="plus">
    <div class="space-y-5" x-data="{
        shareOpen: false,
        shareUrl: @js($shareUrl),
        shareText: @js($shareText),
        copied: false,
        async shareReport() {
            if (navigator.share) {
                try {
                    await navigator.share({ title: @js($brandWordmark.' · '.__('plus.home.reports')), text: this.shareText, url: this.shareUrl });
                    return;
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                }
            }
            this.shareOpen = true;
        },
        async copyLink() {
            try {
                await navigator.clipboard.writeText(this.shareUrl);
                this.copied = true;
                setTimeout(() => this.copied = false, 2200);
            } catch (e) {
                window.prompt(@js(__('plus.reports.share_copy_prompt')), this.shareUrl);
            }
        }
    }">
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
                    onclick="window.print()"
                    class="inline-flex justify-center rounded-xl bg-brand text-white px-5 py-3 font-semibold">
                {{ __('plus.reports.print') }}
            </button>
            <button type="button"
                    @click="shareReport()"
                    class="inline-flex justify-center rounded-xl bg-white text-brand ring-1 ring-brand/20 hover:bg-brand/5 px-5 py-3 font-semibold">
                {{ __('plus.reports.share') }}
            </button>
            <p class="text-xs text-gray-500">{{ __('plus.reports.print_goes') }}</p>
        </div>

        {{-- Compact share sheet (desktop fallback when Web Share is unavailable). --}}
        <div x-show="shareOpen" x-cloak class="print:hidden fixed inset-0 z-[80] flex items-end sm:items-center justify-center p-4"
             role="dialog" aria-modal="true" aria-label="{{ __('plus.reports.share') }}">
            <div class="absolute inset-0 bg-brand/50 backdrop-blur-sm" @click="shareOpen = false"></div>
            <div class="relative w-full max-w-sm rounded-2xl bg-white shadow-xl ring-1 ring-brand/15 p-5 space-y-4"
                 x-transition>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-lg font-bold text-gray-900">{{ __('plus.reports.share') }}</p>
                        <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.share_hint') }}</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-700 text-xl leading-none" @click="shareOpen = false" aria-label="{{ __('borrower.feedback.ok') }}">×</button>
                </div>
                <div class="grid grid-cols-1 gap-2">
                    <a :href="'https://wa.me/?text=' + encodeURIComponent(shareText + ' ' + shareUrl)"
                       target="_blank" rel="noopener"
                       class="inline-flex justify-center rounded-xl bg-brand-gold text-brand font-bold px-4 py-3 text-sm">
                        {{ __('plus.reports.share_whatsapp') }}
                    </a>
                    <a :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(shareUrl)"
                       target="_blank" rel="noopener"
                       class="inline-flex justify-center rounded-xl bg-white ring-1 ring-brand/20 text-brand font-bold px-4 py-3 text-sm">
                        {{ __('plus.reports.share_facebook') }}
                    </a>
                    <button type="button" @click="copyLink()"
                            class="inline-flex justify-center rounded-xl bg-brand text-white font-bold px-4 py-3 text-sm">
                        <span x-show="!copied">{{ __('plus.reports.share_copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('plus.reports.share_copied') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Application print footer only (Chrome Headers/footers OFF in UAT). No URL. --}}
    <footer class="kf-print-running-footer" aria-label="{{ $brandWordmark }}">
        <div class="kf-print-lockup shrink-0">
            <img src="{{ $logoDataUri }}" alt="" width="48" height="48" class="object-contain" aria-hidden="true">
            <span class="kf-print-wordmark">{{ $brandWordmark }}</span>
        </div>
        <p class="min-w-0 flex-1 text-right leading-snug">{{ $footerLine }}</p>
    </footer>
</x-site.borrower-layout>

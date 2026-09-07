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
    $footerLine = __('plus.reports.footer', ['month' => $report['label'] ?? $currentMonth])
        .' · '.brand('legal_name', 'Kopafasta Microfinance Limited');
    $footerLine = trim(preg_replace('#https?://\S+#i', '', $footerLine) ?? $footerLine);
    $footerLine = trim(preg_replace('/\s{2,}/', ' ', $footerLine) ?? $footerLine, " \t\n\r\0\x0B·");
    $print = (bool) ($print ?? false);
@endphp

@if ($print)
    <x-site.print-document
        :title="brand_title(__('plus.home.reports'))"
        :footer-right="$footerLine"
    >
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
            'print' => true,
        ])
    </x-site.print-document>
@else
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

            <a href="{{ route('site.borrower.plus.reports', ['month' => $currentMonth, 'print' => 1]) }}"
               class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold print:hidden">{{ __('plus.reports.print') }}</a>
            <p class="text-xs text-gray-500 print:hidden">{{ __('plus.reports.print_goes') }}</p>
        </div>
    </x-site.borrower-layout>
@endif

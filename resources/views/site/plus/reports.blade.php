<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="dashboard">
    @php
        $money = $report['money'];
        $biz = $report['business'];
        $left = (float) ($money['left'] ?? ((float) $money['in'] - (float) $money['out']));
        $months = $report['months'] ?? [];
        $currentMonth = $report['month'] ?? now()->format('Y-m');
    @endphp
    <div class="space-y-5" @if($print ?? false) x-init="setTimeout(() => window.print(), 300)" @endif>
        <div class="print:hidden space-y-5" x-data="{ monthOpen: false }">
            <x-site.plus-nav />
            <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.reports.heading')" :body="__('plus.reports.hero_body')">
                <button type="button" class="rounded-full bg-white/10 ring-1 ring-white/20 px-4 py-2 text-sm font-semibold" @click="monthOpen = true">
                    ‹ {{ $report['label'] }} ›
                </button>
            </x-site.plus-hero>
            <x-site.action-panel :title="__('plus.reports.pick_month')" open="monthOpen">
                <div class="space-y-1">
                    @foreach ($months as $choice)
                        <a href="{{ route('site.borrower.plus.reports', ['month' => $choice['value']]) }}"
                           class="block rounded-xl px-4 py-3 text-sm {{ $choice['value'] === $currentMonth ? 'bg-brand text-white font-semibold' : 'hover:bg-gray-50' }}">
                            {{ $choice['label'] }} @if($choice['value'] === $currentMonth) ✓ @endif
                        </a>
                    @endforeach
                </div>
            </x-site.action-panel>
        </div>

        @if ($report['thin'] ?? false)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-100 p-4 print:hidden">
                <p class="font-semibold text-gray-900">{{ __('plus.reports.thin_title') }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.thin_body', ['days' => $report['days_recorded'] ?? 0]) }}</p>
                <a href="{{ route('site.borrower.plus.money') }}" class="mt-3 inline-flex text-sm font-semibold text-brand">{{ __('plus.reports.thin_cta') }}</a>
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_left') }}</p>
                <p class="mt-2 text-xl font-extrabold tabular-nums {{ $left < 0 ? 'text-red-700' : 'text-brand' }}" title="{{ format_money($left) }}">{{ format_money_compact($left) }}</p>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_biz') }}</p>
                <p class="mt-2 text-xl font-extrabold tabular-nums text-brand" title="{{ format_money($biz['difference']) }}">{{ ($biz['difference'] >= 0 ? '+' : '').format_money_compact($biz['difference']) }}</p>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_goals') }}</p>
                <p class="mt-2 text-xl font-extrabold tabular-nums" title="{{ format_money($report['goals_added'] ?? 0) }}">+{{ format_money_compact($report['goals_added'] ?? 0) }}</p>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.trust') }}</p>
                <p class="mt-2 text-xl font-extrabold">{{ $report['trust_percent'] ?? 0 }} <span class="text-sm font-semibold text-emerald-700">↑</span></p>
                <p class="text-xs text-gray-500">{{ $report['trust']['label'] ?? '' }}</p>
            </div>
        </div>

        @if (! empty($report['observations']))
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.reports.glance') }}</p>
                @foreach ($report['observations'] as $obs)
                    <div>
                        <p class="font-semibold text-gray-900">{{ $obs['title'] }}</p>
                        <p class="text-sm text-gray-600 mt-0.5">{{ $obs['body'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="kf-print-sheet max-w-3xl mx-auto rounded-2xl bg-white ring-1 ring-brand/15 overflow-hidden">
            <div class="bg-brand text-white px-5 sm:px-8 py-5">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus · {{ __('plus.reports.a4_kicker') }}</p>
                <h2 class="text-2xl font-extrabold mt-1">{{ $report['label'] }}</h2>
                <p class="text-sm text-white/80 mt-1">{{ $report['member_name'] }} · {{ $report['grade'] }} · {{ $report['trust_percent'] ?? 0 }}</p>
            </div>
            <div class="p-5 sm:p-8 space-y-6">
                <p class="text-sm text-gray-700 italic">{{ $report['sentence'] ?? '' }}</p>

                <section>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.money') }}</p>
                    @if ($report['has_money'])
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div><p class="text-gray-500">{{ __('plus.money.in') }}</p><p class="font-bold tabular-nums">{{ format_money($money['in']) }}</p></div>
                            <div><p class="text-gray-500">{{ __('plus.money.out') }}</p><p class="font-bold tabular-nums">{{ format_money($money['out']) }}</p></div>
                            <div><p class="text-gray-500">{{ __('plus.money.left_label') }}</p><p class="font-extrabold tabular-nums">{{ format_money($left) }}</p></div>
                        </div>
                        @if (! empty($report['where']))
                            <p class="mt-4 text-xs font-semibold text-gray-500 uppercase">{{ __('plus.reports.where') }}</p>
                            <div class="mt-2 space-y-1 text-sm">
                                @foreach ($report['where'] as $row)
                                    <div class="flex justify-between"><span>{{ $row['label'] }}</span><span>{{ $row['pct'] }}%</span></div>
                                @endforeach
                            </div>
                        @endif
                    @else
                        <p class="text-sm text-gray-600">{{ __('plus.reports.empty_money') }}</p>
                    @endif
                </section>

                <section>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.business') }}</p>
                    @if ($report['has_business'])
                        <div class="grid grid-cols-3 gap-3 text-sm">
                            <div><p class="text-gray-500">{{ __('plus.business.sold') }}</p><p class="font-bold tabular-nums">{{ format_money($biz['sold']) }}</p></div>
                            <div><p class="text-gray-500">{{ __('plus.business.spent') }}</p><p class="font-bold tabular-nums">{{ format_money($biz['spent']) }}</p></div>
                            <div><p class="text-gray-500">{{ __('plus.business.diff') }}</p><p class="font-extrabold tabular-nums">{{ format_money($biz['difference']) }}</p></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ __('plus.reports.recorded_note') }}</p>
                    @else
                        <p class="text-sm text-gray-600">{{ __('plus.reports.empty_business') }}</p>
                    @endif
                </section>

                <section>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.goals') }}</p>
                    @forelse ($report['goal_cards'] ?? [] as $card)
                        <div class="mb-3">
                            <p class="text-sm font-semibold">{{ $card['icon'] }} {{ $card['title'] }} · {{ $card['percent'] }}%</p>
                            <div class="mt-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full bg-brand rounded-full" style="width: {{ $card['percent'] }}%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ format_money($card['saved']) }} / {{ format_money($card['target']) }}
                                @if ($card['added'] > 0) · +{{ format_money($card['added']) }} @endif
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">{{ __('plus.reports.empty_goals') }}</p>
                    @endforelse
                </section>

                <section>
                    <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-2">{{ __('plus.reports.trust') }}</p>
                    <p class="text-4xl font-black">{{ $report['trust_percent'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600">{{ $report['trust']['label'] ?? '' }} · {{ $report['grade'] }}</p>
                    <p class="text-sm text-gray-600 mt-2">{{ __('plus.reports.trust_help') }}</p>
                </section>

                @if (! empty($report['noticed']))
                    <section>
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.noticed') }}</p>
                        @foreach ($report['noticed'] as $item)
                            <p class="font-semibold text-sm mt-2">{{ $item['title'] }}</p>
                            <p class="text-sm text-gray-600">{{ $item['body'] }}</p>
                        @endforeach
                    </section>
                @endif

                @if (! empty($report['next']))
                    <section class="print:hidden rounded-xl bg-brand/5 p-4">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.reports.next') }}</p>
                        <p class="font-semibold mt-1">{{ $report['next']['title'] }}</p>
                        <a href="{{ $report['next']['url'] }}" class="mt-2 inline-flex text-sm font-semibold text-brand">{{ $report['next']['cta'] }} →</a>
                    </section>
                @endif
            </div>
            <p class="px-5 sm:px-8 pb-5 text-[10px] text-gray-400">{{ __('plus.reports.footer', ['month' => $report['label']]) }}</p>
        </div>

        <a href="{{ route('site.borrower.plus.reports', ['month' => $currentMonth, 'print' => 1]) }}"
           class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold print:hidden">{{ __('plus.reports.print') }}</a>
        <p class="text-xs text-gray-500 print:hidden">{{ __('plus.reports.print_goes') }}</p>

        @if (! empty($report['history']))
            <div class="print:hidden">
                <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold mb-2">{{ __('plus.reports.previous') }}</p>
                <div class="space-y-2">
                    @foreach ($report['history'] as $row)
                        <a href="{{ route('site.borrower.plus.reports', ['month' => $row['month']]) }}" class="flex justify-between rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm">
                            <span>{{ $row['label'] }}</span>
                            <span class="text-gray-500">{{ __('plus.reports.trust') }} {{ $row['trust'] }} →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-site.borrower-layout>

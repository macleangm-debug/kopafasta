<div class="kf-print-sheet mx-auto rounded-2xl bg-white ring-1 ring-brand/15 overflow-hidden print:rounded-none print:ring-0 print:overflow-visible">
    @if ($print)
        <div class="kf-print-header kf-print-avoid bg-gradient-to-br from-brand via-brand-light to-emerald-800 text-white px-5 sm:px-8 py-5 rounded-2xl">
            <div class="flex items-center justify-between gap-3">
                <x-site.brand-mark size="sm" variant="light" />
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold shrink-0">{{ __('plus.reports.print_plus_label') }}</p>
            </div>
            <p class="mt-2 text-sm text-white/80">{{ __('plus.reports.a4_kicker') }}</p>
            <p class="mt-2 text-center text-sm text-white/90">
                {{ $report['member_name'] }}
                @if (! empty($report['membership_number']))
                    · {{ $report['membership_number'] }}
                @endif
                · {{ $report['grade'] }}
            </p>
            <h2 class="mt-1 text-xl sm:text-2xl font-extrabold text-center">{{ $report['label'] }}</h2>
            <p class="text-sm text-white/80 mt-1 text-center">{{ __('plus.reports.trust_line', ['percent' => $report['trust_percent'] ?? 0, 'label' => $report['trust']['label'] ?? '']) }}</p>
            <p class="mt-2 text-center text-xs sm:text-sm text-white/85 truncate px-2" title="{{ $businessContext }}">
                {{ __('plus.reports.business_context', ['name' => $businessContext]) }}
            </p>
            <p class="mt-1 text-center text-[11px] text-white/70">{{ __('plus.reports.generated', ['date' => $report['generated_at'] ?? now()->toDateTimeString()]) }}</p>
        </div>
    @else
    <div class="kf-print-avoid bg-gradient-to-br from-brand via-brand-light to-emerald-800 text-white px-5 sm:px-8 py-4">
        <div class="flex items-center justify-between gap-3">
            <div class="inline-flex items-center gap-2 min-w-0">
                <x-site.brand-mark size="sm" variant="light" />
                <p class="text-sm font-bold truncate">{{ brand_name() }}</p>
            </div>
            <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold shrink-0">Kopafasta Plus</p>
        </div>
        <p class="mt-2 text-sm text-white/80">{{ __('plus.reports.a4_kicker') }}</p>
        <p class="mt-2 text-center text-sm text-white/90">
            {{ $report['member_name'] }}
            @if (! empty($report['membership_number']))
                · {{ $report['membership_number'] }}
            @endif
            · {{ $report['grade'] }}
        </p>
        <div class="mt-1 flex items-center justify-between gap-3">
            @if ($older)
                <a href="{{ route('site.borrower.plus.reports', ['month' => $older['value']]) }}"
                   class="print:hidden size-9 grid place-items-center rounded-full bg-white/10 ring-1 ring-white/20 text-lg font-bold hover:bg-white/20"
                   aria-label="{{ $older['label'] }}">‹</a>
            @else
                <span class="print:hidden size-9"></span>
            @endif
            <h2 class="text-xl sm:text-2xl font-extrabold text-center flex-1">{{ $report['label'] }}</h2>
            @if ($newer)
                <a href="{{ route('site.borrower.plus.reports', ['month' => $newer['value']]) }}"
                   class="print:hidden size-9 grid place-items-center rounded-full bg-white/10 ring-1 ring-white/20 text-lg font-bold hover:bg-white/20"
                   aria-label="{{ $newer['label'] }}">›</a>
            @else
                <span class="print:hidden size-9"></span>
            @endif
        </div>
        <p class="text-sm text-white/80 mt-1 text-center">{{ __('plus.reports.trust_line', ['percent' => $report['trust_percent'] ?? 0, 'label' => $report['trust']['label'] ?? '']) }}</p>
        <p class="mt-2 text-center text-xs sm:text-sm text-white/85 truncate px-2" title="{{ $businessContext }}">
            {{ __('plus.reports.business_context', ['name' => $businessContext]) }}
        </p>
        <p class="mt-1 text-center text-[11px] text-white/70">{{ __('plus.reports.generated', ['date' => $report['generated_at'] ?? now()->toDateTimeString()]) }}</p>

        @if (count($months) > 1)
            <div class="print:hidden mt-4" x-data="{
                yearOpen: false,
                monthOpen: false,
                year: {{ (int) substr($currentMonth, 0, 4) }},
                months: {{ \Illuminate\Support\Js::from($months) }},
                years: {{ \Illuminate\Support\Js::from($report['years'] ?? collect($months)->pluck('year')->unique()->values()) }},
                monthsForYear() { return this.months.filter(m => Number(String(m.value).slice(0,4)) === Number(this.year)); }
            }">
                <div class="grid grid-cols-2 gap-2">
                    <div class="relative">
                        <button type="button" class="w-full rounded-xl bg-white/10 ring-1 ring-white/20 px-3 py-2.5 text-sm text-left"
                                @click="window.matchMedia('(max-width: 1023px)').matches ? yearOpen = true : yearOpen = !yearOpen">
                            <span x-text="year"></span>
                        </button>
                        <div class="hidden lg:block absolute z-20 mt-1 w-full rounded-xl bg-white text-gray-900 shadow-xl ring-1 ring-gray-200 py-1 max-h-56 overflow-y-auto"
                             x-cloak x-show="yearOpen" @click.outside="yearOpen = false">
                            <template x-for="y in years" :key="y">
                                <button type="button" class="block w-full text-left px-3 py-2 text-sm hover:bg-gray-50" @click="year = y; yearOpen = false" x-text="y"></button>
                            </template>
                        </div>
                        <x-site.bottom-sheet :title="__('plus.reports.pick_year')" open="yearOpen">
                            <template x-for="y in years" :key="'y-'+y">
                                <button type="button" class="block w-full text-left px-4 py-3 rounded-xl text-sm hover:bg-gray-50" @click="year = y; yearOpen = false" x-text="y"></button>
                            </template>
                        </x-site.bottom-sheet>
                    </div>
                    <div class="relative">
                        <button type="button" class="w-full rounded-xl bg-white/10 ring-1 ring-white/20 px-3 py-2.5 text-sm text-left"
                                @click="window.matchMedia('(max-width: 1023px)').matches ? monthOpen = true : monthOpen = !monthOpen">
                            {{ $report['label'] }}
                        </button>
                        <div class="hidden lg:block absolute z-20 mt-1 w-full rounded-xl bg-white text-gray-900 shadow-xl ring-1 ring-gray-200 py-1 max-h-56 overflow-y-auto"
                             x-cloak x-show="monthOpen" @click.outside="monthOpen = false">
                            <template x-for="m in monthsForYear()" :key="m.value">
                                <a :href="'{{ route('site.borrower.plus.reports') }}?month=' + m.value" class="block px-3 py-2 text-sm hover:bg-gray-50" x-text="m.label"></a>
                            </template>
                        </div>
                        <x-site.bottom-sheet :title="__('plus.reports.pick_month')" open="monthOpen">
                            <template x-for="m in monthsForYear()" :key="'m-'+m.value">
                                <a :href="'{{ route('site.borrower.plus.reports') }}?month=' + m.value" class="block px-4 py-3 rounded-xl text-sm hover:bg-gray-50" x-text="m.label"></a>
                            </template>
                        </x-site.bottom-sheet>
                    </div>
                </div>
            </div>
        @endif
    </div>
    @endif

    <div class="p-5 sm:p-8 space-y-6 pb-16 print:pb-20">
        <div class="kf-print-avoid grid grid-cols-2 gap-3">
            <div class="kf-print-section rounded-xl bg-brand/5 p-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.money.in') }}</p>
                <p class="mt-1 text-lg font-extrabold tabular-nums text-brand">{{ format_money_compact($money['in'] ?? 0) }}</p>
            </div>
            <div class="kf-print-section rounded-xl bg-brand/5 p-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.money.out') }}</p>
                <p class="mt-1 text-lg font-extrabold tabular-nums text-gray-900">{{ format_money_compact($money['out'] ?? 0) }}</p>
            </div>
            <div class="kf-print-section rounded-xl bg-brand/5 p-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_left') }}</p>
                <p class="mt-1 text-lg font-extrabold tabular-nums {{ $left < 0 ? 'text-red-700' : 'text-brand' }}">{{ format_money_compact($left) }}</p>
            </div>
            <div class="kf-print-section rounded-xl bg-brand/5 p-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_goals') }}</p>
                <p class="mt-1 text-lg font-extrabold tabular-nums">+{{ format_money_compact($report['goals_added'] ?? 0) }}</p>
            </div>
        </div>

        @if ($review !== [])
            <section class="kf-print-section rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-4 sm:p-5">
                <p class="kf-print-keep-with-next text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.your_review') }}</p>
                <div class="space-y-3">
                    @foreach ($review as $obs)
                        <div class="kf-print-avoid">
                            <p class="font-bold text-gray-900">{{ $obs['title'] }}</p>
                            <p class="text-sm text-gray-600 mt-0.5 leading-snug">{{ $obs['body'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if (! empty($report['sentence']))
            <p class="kf-print-avoid text-sm text-gray-700 italic rounded-2xl bg-brand-gold/10 ring-1 ring-brand-gold/20 px-4 py-3">{{ $report['sentence'] }}</p>
        @endif

        <section class="kf-print-section rounded-2xl bg-emerald-50/80 ring-1 ring-brand/10 p-4 sm:p-5">
            <p class="kf-print-keep-with-next text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.money') }}</p>
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
                            <div class="flex justify-between gap-3"><span class="min-w-0 truncate">{{ $row['label'] }}</span><span class="shrink-0">{{ $row['pct'] }}%</span></div>
                        @endforeach
                    </div>
                @endif
            @else
                <p class="text-sm text-gray-600">{{ __('plus.reports.empty_money') }}</p>
            @endif
        </section>

        <section class="kf-print-section rounded-2xl bg-white ring-1 ring-brand/10 p-4 sm:p-5">
            <p class="kf-print-keep-with-next text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.business') }}</p>
            <p class="text-sm text-gray-600 mb-3">{{ $businessContext }}</p>
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

        <section class="kf-print-section rounded-2xl bg-brand-gold/10 ring-1 ring-brand-gold/25 p-4 sm:p-5">
            <p class="kf-print-keep-with-next text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.goals') }}</p>
            @forelse ($report['goal_cards'] ?? [] as $card)
                <div class="kf-print-goal-row mb-3">
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

        <section class="kf-print-section rounded-2xl bg-brand/5 ring-1 ring-brand/15 p-4 sm:p-5">
            <p class="kf-print-keep-with-next text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-2">{{ __('plus.reports.trust') }}</p>
            <p class="text-4xl font-black text-brand">{{ $report['trust_percent'] ?? 0 }}</p>
            <p class="text-sm text-gray-600">{{ $report['trust']['label'] ?? '' }} · {{ $report['grade'] }}</p>
            <p class="text-sm text-gray-600 mt-2 leading-snug">{{ __('plus.reports.trust_help') }}</p>
        </section>

        @if (! empty($report['next_three']))
            <section class="kf-print-section rounded-2xl bg-brand-muted/50 ring-1 ring-brand/10 p-4 sm:p-5">
                <p class="kf-print-keep-with-next text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.reports.three_things') }}</p>
                <ol class="mt-3 space-y-2 list-decimal list-inside text-sm text-gray-800">
                    @foreach ($report['next_three'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ol>
            </section>
        @elseif (! empty($report['next']) && ! $print)
            <section class="print:hidden rounded-2xl bg-brand/5 ring-1 ring-brand/10 p-4">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.reports.next') }}</p>
                <p class="font-semibold mt-1">{{ $report['next']['title'] }}</p>
                <a href="{{ $report['next']['url'] }}" class="mt-2 inline-flex text-sm font-semibold text-brand">{{ $report['next']['cta'] }} →</a>
            </section>
        @endif
    </div>

    @if ($print)
        <div class="kf-print-app-footer px-5 sm:px-8 pb-5 flex items-center justify-between gap-3 text-[10px] text-gray-500">
            <x-site.brand-mark size="sm" />
            <p>{{ __('plus.reports.footer_confidential') }}</p>
        </div>
    @else
        <div class="px-5 sm:px-8 pb-5 flex items-center justify-between gap-3 text-[10px] text-gray-400 print:hidden">
            <x-site.brand-mark size="sm" />
            <p>{{ __('plus.reports.footer_confidential') }}</p>
        </div>
    @endif
</div>

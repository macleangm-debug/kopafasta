<x-site.borrower-layout :title="brand_title(__('plus.home.reports'))" active="plus">
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
    @endphp
    <div class="space-y-5" @if($print ?? false) x-init="setTimeout(() => window.print(), 300)" @endif>
        <div class="print:hidden">
            <x-site.plus-nav />
        </div>

        @if ($report['thin'] ?? false)
            <div class="rounded-2xl bg-amber-50 ring-1 ring-amber-100 p-4 print:hidden">
                <p class="font-semibold text-gray-900">{{ __('plus.reports.thin_title') }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ __('plus.reports.thin_body', ['days' => $report['days_recorded'] ?? 0]) }}</p>
                <a href="{{ route('site.borrower.plus.money') }}" class="mt-3 inline-flex text-sm font-semibold text-brand">{{ __('plus.reports.thin_cta') }}</a>
            </div>
        @endif

        <div class="kf-print-sheet max-w-3xl mx-auto rounded-2xl bg-white ring-1 ring-brand/15 overflow-hidden">
            <div class="bg-gradient-to-br from-brand via-brand-light to-emerald-800 text-white px-5 sm:px-8 py-6">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand-gold font-bold">Kopafasta Plus</p>
                <p class="mt-1 text-sm text-white/80">{{ __('plus.reports.a4_kicker') }}</p>
                <p class="mt-3 text-center text-sm text-white/90">
                    {{ $report['member_name'] }}
                    @if (! empty($report['membership_number']))
                        · {{ $report['membership_number'] }}
                    @endif
                    · {{ $report['grade'] }}
                </p>
                <div class="mt-2 flex items-center justify-between gap-3">
                    @if ($older)
                        <a href="{{ route('site.borrower.plus.reports', ['month' => $older['value']]) }}"
                           class="print:hidden size-9 grid place-items-center rounded-full bg-white/10 ring-1 ring-white/20 text-lg font-bold hover:bg-white/20"
                           aria-label="{{ $older['label'] }}">‹</a>
                    @else
                        <span class="print:hidden size-9"></span>
                    @endif
                    <h2 class="text-2xl font-extrabold text-center flex-1">{{ $report['label'] }}</h2>
                    @if ($newer)
                        <a href="{{ route('site.borrower.plus.reports', ['month' => $newer['value']]) }}"
                           class="print:hidden size-9 grid place-items-center rounded-full bg-white/10 ring-1 ring-white/20 text-lg font-bold hover:bg-white/20"
                           aria-label="{{ $newer['label'] }}">›</a>
                    @else
                        <span class="print:hidden size-9"></span>
                    @endif
                </div>
                <p class="text-sm text-white/80 mt-1 text-center">{{ __('plus.reports.trust_line', ['percent' => $report['trust_percent'] ?? 0, 'label' => $report['trust']['label'] ?? '']) }}</p>
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
                                <div class="hidden lg:block absolute z-20 mt-1 w-full rounded-xl bg-white text-gray-900 shadow-xl ring-1 ring-gray-200 py-1"
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

            <div class="p-5 sm:p-8 space-y-6">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-brand/5 p-3">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.money.in') }}</p>
                        <p class="mt-1 text-lg font-extrabold tabular-nums text-brand">{{ format_money_compact($money['in'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-brand/5 p-3">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.money.out') }}</p>
                        <p class="mt-1 text-lg font-extrabold tabular-nums text-gray-900">{{ format_money_compact($money['out'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-brand/5 p-3">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_left') }}</p>
                        <p class="mt-1 text-lg font-extrabold tabular-nums {{ $left < 0 ? 'text-red-700' : 'text-brand' }}">{{ format_money_compact($left) }}</p>
                    </div>
                    <div class="rounded-xl bg-brand/5 p-3">
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-bold">{{ __('plus.reports.kpi_goals') }}</p>
                        <p class="mt-1 text-lg font-extrabold tabular-nums">+{{ format_money_compact($report['goals_added'] ?? 0) }}</p>
                    </div>
                </div>

                @if ($review !== [])
                    <section>
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold mb-3">{{ __('plus.reports.your_review') }}</p>
                        <div class="space-y-3">
                            @foreach ($review as $obs)
                                <div>
                                    <p class="font-bold text-gray-900">{{ $obs['title'] }}</p>
                                    <p class="text-sm text-gray-600 mt-0.5 leading-snug">{{ $obs['body'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

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
                    <p class="text-4xl font-black text-brand">{{ $report['trust_percent'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600">{{ $report['trust']['label'] ?? '' }} · {{ $report['grade'] }}</p>
                    <p class="text-sm text-gray-600 mt-2 leading-snug">{{ __('plus.reports.trust_help') }}</p>
                </section>

                @if (! empty($report['next_three']))
                    <section class="rounded-xl bg-brand/5 p-4">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.reports.three_things') }}</p>
                        <ol class="mt-3 space-y-2 list-decimal list-inside text-sm text-gray-800">
                            @foreach ($report['next_three'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ol>
                    </section>
                @elseif (! empty($report['next']))
                    <section class="print:hidden rounded-xl bg-brand/5 p-4">
                        <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.reports.next') }}</p>
                        <p class="font-semibold mt-1">{{ $report['next']['title'] }}</p>
                        <a href="{{ $report['next']['url'] }}" class="mt-2 inline-flex text-sm font-semibold text-brand">{{ $report['next']['cta'] }} →</a>
                    </section>
                @endif
            </div>
            <p class="px-5 sm:px-8 pb-5 text-[10px] text-gray-400">{{ __('plus.reports.footer', ['month' => $report['label']]) }} · {{ __('plus.reports.generated', ['date' => $report['generated_at'] ?? now()->toDateTimeString()]) }}</p>
        </div>

        <a href="{{ route('site.borrower.plus.reports', ['month' => $currentMonth, 'print' => 1]) }}"
           class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold print:hidden">{{ __('plus.reports.print') }}</a>
        <p class="text-xs text-gray-500 print:hidden">{{ __('plus.reports.print_goes') }}</p>
    </div>
</x-site.borrower-layout>

@php
    $amount = (float) ($price['amount'] ?? 0);
    $isYearly = ($cycle ?? 'yearly') === 'yearly';
    $monthlyEquiv = $isYearly && $amount > 0 ? $amount / 12 : null;

    $benefits = [
        ['title' => __('site.plus.benefit_behaviour_title'), 'body' => __('site.plus.benefit_behaviour_body')],
        ['title' => __('plus.home.money'), 'body' => __('site.plus.room_money')],
        ['title' => __('plus.home.business'), 'body' => __('site.plus.room_business')],
        ['title' => __('plus.home.goals'), 'body' => __('site.plus.room_goals')],
        ['title' => __('plus.home.reports'), 'body' => __('site.plus.room_reports')],
        ['title' => __('site.plus.benefit_offers_title'), 'body' => __('site.plus.benefit_offers_body')],
        ['title' => __('plus.home.learn'), 'body' => __('site.plus.benefit_learning_body')],
    ];
@endphp

<x-site.layout :title="brand_title(__('site.plus.meta_title'))" :description="__('site.plus.meta_desc')">
    <section class="relative overflow-hidden premium-gradient py-8 sm:py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-[#127A5F] to-[#082f27] text-white shadow-[0_24px_60px_rgba(8,47,39,0.24)] ring-1 ring-brand-gold/30">
                <div class="absolute inset-0 opacity-[0.16] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
                <div class="relative grid lg:grid-cols-2 gap-8 items-center px-6 sm:px-10 py-8 sm:py-10">
                    <div class="text-left">
                        <p class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.2em] text-brand-gold">
                            <span class="text-lg tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
                            Kopafasta Plus
                        </p>
                        <h1 class="mt-3 text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">{{ __('site.plus.hero_title') }}</h1>
                        <p class="mt-3 text-white/80 max-w-xl leading-relaxed">{{ __('site.plus.hero_body') }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="{{ route('site.borrower.plus.home') }}" class="inline-flex rounded-xl bg-brand-gold text-brand px-5 py-3 font-extrabold">{{ __('site.plus.join') }}</a>
                            <a href="#benefits" class="inline-flex rounded-xl bg-white/10 ring-1 ring-white/25 px-5 py-3 font-semibold text-white">{{ __('site.plus.see_how') }}</a>
                        </div>
                        <p class="mt-4 text-sm text-white/60">{{ __('site.plus.optional') }}</p>
                    </div>
                    <ul class="grid sm:grid-cols-2 gap-3">
                        @foreach ([
                            __('site.plus.teaser_benefit_1'),
                            __('site.plus.teaser_benefit_2'),
                            __('site.plus.teaser_benefit_3'),
                            __('site.plus.teaser_benefit_4'),
                        ] as $line)
                            <li class="rounded-2xl bg-white/10 ring-1 ring-white/15 px-4 py-3.5 text-sm font-semibold">
                                <span class="text-brand-gold tracking-[-0.12em] font-black" aria-hidden="true">›››</span>
                                <span class="mt-1.5 block">{{ $line }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section id="benefits" class="py-10 lg:py-12 bg-white"
             x-data="{
                scrollByCard(dir) {
                    const track = this.$refs.track;
                    if (!track) return;
                    const slide = track.querySelector('[data-plus-benefit]');
                    const step = (slide ? slide.getBoundingClientRect().width : 300) + 16;
                    track.scrollBy({ left: dir * step, behavior: 'smooth' });
                },
             }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-left max-w-2xl mb-6">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.plus.rooms_title') }}</h2>
                <p class="mt-2 text-gray-600">{{ __('site.plus.rooms_body') }}</p>
            </div>

            <div class="relative">
                <div
                    x-ref="track"
                    class="overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory scroll-smooth scrollbar-none"
                    style="-webkit-overflow-scrolling: touch;"
                >
                    <div class="flex gap-4 w-max items-stretch">
                        @foreach ($benefits as $benefit)
                            <div data-plus-benefit class="snap-start shrink-0 w-[min(100%,calc(100vw-3rem))] sm:w-[min(320px,calc(50vw-2.5rem))] lg:w-[min(340px,calc(33.333vw-2.5rem))]">
                                <div class="h-full min-h-[11rem] rounded-2xl bg-[#f7faf8] ring-1 ring-brand/10 p-5 sm:p-6 flex flex-col shadow-[0_12px_30px_rgba(8,47,39,0.05)]">
                                    <span class="text-brand-gold font-black text-xl tracking-[-0.14em]" aria-hidden="true">›››</span>
                                    <h3 class="mt-3 text-lg font-bold text-gray-900">{{ $benefit['title'] }}</h3>
                                    <p class="mt-2 text-sm text-gray-600 leading-relaxed flex-1">{{ $benefit['body'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-between gap-3">
                    <div class="flex gap-2">
                        <button type="button" @click="scrollByCard(-1)"
                                class="size-10 rounded-full bg-white ring-1 ring-gray-200 text-brand font-bold" aria-label="Previous">‹</button>
                        <button type="button" @click="scrollByCard(1)"
                                class="size-10 rounded-full bg-white ring-1 ring-gray-200 text-brand font-bold" aria-label="Next">›</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-10 lg:py-12 bg-[#f7faf8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-[#127A5F] to-[#082f27] text-white px-6 sm:px-10 py-8 sm:py-9 shadow-[0_24px_60px_rgba(8,47,39,0.24)] ring-1 ring-brand-gold/35">
                <div class="absolute inset-0 opacity-[0.14] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
                <div class="relative grid lg:grid-cols-[1.2fr_1fr] gap-8 items-center text-left">
                    <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] text-brand-gold font-bold">Kopafasta Plus</p>
                        <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="text-4xl sm:text-5xl font-black tabular-nums tracking-tight">{{ format_money($amount) }}</p>
                            <p class="text-base font-semibold text-white/75">/ {{ $isYearly ? __('site.plus.per_year') : __('site.plus.per_month') }}</p>
                            @if ($monthlyEquiv !== null)
                                <p class="text-sm text-white/70 w-full sm:w-auto">· {{ __('site.plus.equiv_month_compact', ['amount' => format_money($monthlyEquiv)]) }}</p>
                            @endif
                        </div>
                        <ul class="mt-5 grid sm:grid-cols-2 gap-2 text-sm text-white/90">
                            @foreach (__('site.plus.includes') as $item)
                                <li class="flex gap-2"><span class="text-brand-gold shrink-0 font-bold">›</span> {{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="lg:text-right">
                        <a href="{{ route('site.borrower.plus.home') }}" class="inline-flex rounded-xl bg-brand-gold text-brand px-7 py-3.5 font-extrabold shadow-md">{{ __('site.plus.join') }}</a>
                        <p class="mt-3 text-xs text-white/60 lg:ml-auto lg:max-w-xs">{{ __('site.plus.optional') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>

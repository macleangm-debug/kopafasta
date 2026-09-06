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
    <section class="relative overflow-hidden premium-gradient py-12 lg:py-16">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center justify-center rounded-full bg-brand text-brand-gold px-4 py-2 ring-1 ring-brand-gold/30">
                <span class="text-2xl font-black tracking-[-0.18em] leading-none" aria-hidden="true">›››</span>
            </div>
            <p class="mt-4 text-xs uppercase tracking-[0.22em] text-brand font-bold">Kopafasta Plus</p>
            <h1 class="mt-3 text-3xl sm:text-5xl font-black text-gray-900 leading-tight">{{ __('site.plus.hero_title') }}</h1>
            <p class="mt-4 text-lg text-gray-600 max-w-xl mx-auto">{{ __('site.plus.hero_body') }}</p>
            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <a href="{{ route('site.borrower.plus.home') }}" class="inline-flex rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('site.plus.join') }}</a>
                <a href="#benefits" class="inline-flex rounded-xl bg-white ring-1 ring-gray-200 px-5 py-3 font-semibold text-gray-800">{{ __('site.plus.see_how') }}</a>
            </div>
            <p class="mt-4 text-sm text-gray-500">{{ __('site.plus.optional') }}</p>
        </div>
    </section>

    <section id="benefits" class="py-14 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ __('site.plus.rooms_title') }}</h2>
                <p class="mt-2 text-gray-600">{{ __('site.plus.rooms_body') }}</p>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach ($benefits as $benefit)
                    <div class="rounded-2xl bg-[#f7faf8] ring-1 ring-brand/10 p-5 sm:p-6 flex flex-col min-h-[11rem] shadow-[0_12px_30px_rgba(8,47,39,0.05)]">
                        <span class="text-brand-gold font-black text-xl tracking-[-0.14em]" aria-hidden="true">›››</span>
                        <h3 class="mt-3 text-lg font-bold text-gray-900">{{ $benefit['title'] }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed flex-1">{{ $benefit['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-14 bg-gradient-to-b from-[#f7faf8] to-white">
        <div class="max-w-lg mx-auto px-4">
            <div class="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-brand via-[#127A5F] to-[#082f27] text-white p-7 sm:p-9 text-center shadow-[0_24px_60px_rgba(8,47,39,0.28)] ring-1 ring-brand-gold/35">
                <div class="absolute inset-0 opacity-[0.16] pointer-events-none" style="background-image:url(\"data:image/svg+xml,%3Csvg width='72' height='48' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M6 36l14-24 14 24M30 36l14-24 14 24' fill='none' stroke='%23f5c842' stroke-opacity='0.55' stroke-width='2'/%3E%3C/svg%3E\"); background-size:72px 48px;"></div>
                <p class="relative text-[10px] uppercase tracking-[0.2em] text-brand-gold font-bold">Kopafasta Plus</p>
                <p class="relative mt-5 text-5xl font-black tabular-nums tracking-tight">
                    {{ format_money($amount) }}
                </p>
                <p class="relative mt-1 text-sm font-semibold text-white/75">
                    / {{ $isYearly ? __('site.plus.per_year') : __('site.plus.per_month') }}
                </p>
                @if ($monthlyEquiv !== null)
                    <p class="relative mt-3 text-sm text-white/70">
                        {{ __('site.plus.equiv_month', ['amount' => format_money($monthlyEquiv)]) }}
                    </p>
                @endif
                <ul class="relative mt-7 space-y-2.5 text-sm text-white/90 text-left max-w-sm mx-auto">
                    @foreach (__('site.plus.includes') as $item)
                        <li class="flex gap-2"><span class="text-brand-gold shrink-0 font-bold">›</span> {{ $item }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('site.borrower.plus.home') }}" class="relative mt-8 inline-flex rounded-xl bg-brand-gold text-brand px-7 py-3.5 font-extrabold shadow-md">{{ __('site.plus.join') }}</a>
                <p class="relative mt-4 text-xs text-white/60">{{ __('site.plus.optional') }}</p>
            </div>
        </div>
    </section>
</x-site.layout>

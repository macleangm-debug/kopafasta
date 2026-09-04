<x-site.borrower-layout :title="brand_title(__('plus.home.offers'))" active="plus">
    @php $best = $offers->first(); $rest = $offers->slice(1); @endphp
    <div class="space-y-5" x-data="{ claimOpen: false }">
        <x-site.plus-nav />
        <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.offers.title')" :body="__('plus.offers.hero_body')" />

        @if ($best)
            <div class="rounded-2xl kf-premium-panel p-5 sm:p-6">
                <div class="relative flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap gap-2 items-center">
                            <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">✦ {{ __('plus.offers.best') }}</p>
                            <p class="text-[10px] uppercase tracking-widest text-white/55 font-bold">{{ $best->tier }}</p>
                        </div>
                        <h2 class="font-extrabold text-white mt-2 text-xl sm:text-2xl tracking-tight">{{ $best->localizedTitle() }}</h2>
                        <p class="text-sm text-white/85 mt-1">{{ $best->localizedBody() }}</p>
                        @if ($best->ends_at)
                            <p class="text-xs text-white/65 mt-2">{{ __('plus.offers.until', ['date' => $best->ends_at->locale(app()->getLocale())->isoFormat('D MMM YYYY')]) }}</p>
                        @endif
                        <div class="mt-4 flex flex-wrap gap-2">
                            <form method="post" action="{{ route('site.borrower.plus.offers.open', $best) }}">
                                @csrf
                                <button class="rounded-xl bg-brand-gold hover:brightness-95 text-brand px-4 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.offers.view') }}</button>
                            </form>
                            @unless ($claimed[$best->id] ?? false)
                                <button type="button" @click="claimOpen = true" class="rounded-xl bg-white/10 hover:bg-white/15 text-white px-4 py-2.5 text-sm font-bold ring-1 ring-white/25">{{ __('plus.offers.claim') }}</button>
                            @endunless
                        </div>
                    </div>
                    <div class="kf-welcome-art kf-welcome-art-rewards shrink-0 opacity-90 hidden sm:block" aria-hidden="true">
                        @include('components.site.illustrations.product', ['type' => 'offers'])
                    </div>
                </div>
            </div>
            <x-site.action-panel title="{{ __('plus.offers.claim') }}" open="claimOpen">
                <p class="text-sm text-gray-600">{{ $best->localizedTitle() }}</p>
                <form method="post" action="{{ route('site.borrower.plus.offers.claim', $best) }}" class="mt-4">
                    @csrf
                    <button class="w-full rounded-xl bg-brand-gold hover:brightness-95 text-brand py-3 font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.offers.claim') }}</button>
                </form>
            </x-site.action-panel>
        @else
            <x-site.empty-state compact icon="🎁" :title="__('plus.offers.empty')" />
        @endif

        @if ($rest->isNotEmpty())
            <p class="text-[10px] uppercase tracking-[0.16em] text-brand-gold font-bold">{{ __('plus.offers.others') }}</p>
            @foreach ($rest as $offer)
                <div class="rounded-2xl glass-card ring-1 ring-brand-gold/20 p-5">
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-bold">{{ $offer->tier }}</p>
                    <p class="font-semibold mt-1 text-gray-900">{{ $offer->localizedTitle() }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $offer->localizedBody() }}</p>
                    @unless ($claimed[$offer->id] ?? false)
                        <form method="post" action="{{ route('site.borrower.plus.offers.claim', $offer) }}" class="mt-3">
                            @csrf
                            <button class="rounded-xl bg-brand-gold hover:brightness-95 text-brand px-4 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40">{{ __('plus.offers.claim') }}</button>
                        </form>
                    @endunless
                </div>
            @endforeach
        @endif
    </div>
</x-site.borrower-layout>

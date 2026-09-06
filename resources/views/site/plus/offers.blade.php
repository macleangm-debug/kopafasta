@php
    $best = $offers->first();
    $rest = $offers->slice(1);
    $viewId = (int) request('view');
    $viewOffer = $offers->firstWhere('id', $viewId) ?: ($claimedOffers ?? collect())->firstWhere('id', $viewId);
    $tab = request('tab') === 'claimed' ? 'claimed' : 'available';
@endphp
<x-site.borrower-layout :title="brand_title(__('plus.home.offers'))" active="plus">
    <div class="space-y-5" x-data="{
        viewOpen: {{ $viewOffer ? 'true' : 'false' }},
        phase: 'detail',
        detailTitle: @js($viewOffer?->localizedTitle()),
        detailBody: @js($viewOffer?->localizedBody()),
        detailUntil: @js($viewOffer?->ends_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY')),
        detailTier: @js($viewOffer?->tier),
        claimId: {{ $viewOffer?->id ?: 'null' }},
        claimedAlready: {{ ($viewOffer && ($claimed[$viewOffer->id] ?? false)) || $tab === 'claimed' ? 'true' : 'false' }},
        openOffer(id, title, body, until, tier, already) {
            this.claimId = id;
            this.detailTitle = title;
            this.detailBody = body;
            this.detailUntil = until;
            this.detailTier = tier;
            this.claimedAlready = !!already;
            this.phase = 'detail';
            this.viewOpen = true;
        }
    }">
        <x-site.plus-nav />
        <x-site.plus-hero kicker="Kopafasta Plus" :title="__('plus.offers.title')" :body="__('plus.offers.hero_body')" />

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        <div class="flex gap-2">
            <a href="{{ route('site.borrower.plus.offers') }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $tab === 'available' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700' }}">{{ __('plus.offers.available_tab') }}</a>
            <a href="{{ route('site.borrower.plus.offers', ['tab' => 'claimed']) }}" class="rounded-full px-4 py-2 text-sm font-semibold {{ $tab === 'claimed' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-700' }}">{{ __('plus.offers.claimed_tab') }}</a>
        </div>

        @if ($tab === 'claimed')
            @forelse (($claimedOffers ?? collect()) as $offer)
                <div class="rounded-2xl glass-card ring-1 ring-brand-gold/20 p-5">
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-bold">{{ __('plus.offers.status_claimed') }}</p>
                    <p class="font-semibold mt-1 text-gray-900">{{ $offer->localizedTitle() }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $offer->localizedBody() }}</p>
                    <button type="button" class="mt-3 rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-bold"
                            @click="openOffer({{ $offer->id }}, @js($offer->localizedTitle()), @js($offer->localizedBody()), @js($offer->ends_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY')), @js($offer->tier), true)">
                        {{ __('plus.offers.view') }}
                    </button>
                </div>
            @empty
                <x-site.empty-state compact icon="🎁" :title="__('plus.offers.claimed_empty')" />
            @endforelse
        @else
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
                                <button type="button" class="rounded-xl bg-brand-gold hover:brightness-95 text-brand px-4 py-2.5 text-sm font-bold shadow-sm ring-1 ring-brand-gold/40"
                                        @click="openOffer({{ $best->id }}, @js($best->localizedTitle()), @js($best->localizedBody()), @js($best->ends_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY')), @js($best->tier), {{ ($claimed[$best->id] ?? false) ? 'true' : 'false' }})">
                                    {{ __('plus.offers.view') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <x-site.empty-state compact icon="🎁" :title="__('plus.offers.empty')" />
            @endif

            @foreach ($rest as $offer)
                <div class="rounded-2xl glass-card ring-1 ring-brand-gold/20 p-5">
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-bold">{{ $offer->tier }}</p>
                    <p class="font-semibold mt-1 text-gray-900">{{ $offer->localizedTitle() }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $offer->localizedBody() }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-bold"
                                @click="openOffer({{ $offer->id }}, @js($offer->localizedTitle()), @js($offer->localizedBody()), @js($offer->ends_at?->locale(app()->getLocale())->isoFormat('D MMM YYYY')), @js($offer->tier), {{ ($claimed[$offer->id] ?? false) ? 'true' : 'false' }})">
                            {{ __('plus.offers.view') }}
                        </button>
                    </div>
                </div>
            @endforeach
        @endif

        <x-site.action-panel title="{{ __('plus.offers.view') }}" open="viewOpen" size="md">
            <div x-show="phase === 'detail'">
                <p class="text-[10px] uppercase tracking-widest text-brand font-bold" x-text="detailTier"></p>
                <h3 class="mt-1 text-lg font-bold text-gray-900" x-text="detailTitle"></h3>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="rounded-xl bg-gray-50 px-3 py-2"><dt class="text-xs text-gray-500 font-semibold">{{ __('plus.offers.benefit') }}</dt><dd class="mt-0.5" x-text="detailBody"></dd></div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2"><dt class="text-xs text-gray-500 font-semibold">{{ __('plus.offers.eligibility') }}</dt><dd class="mt-0.5">{{ __('plus.offers.eligibility_plus') }}</dd></div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2"><dt class="text-xs text-gray-500 font-semibold">{{ __('plus.offers.action_required') }}</dt><dd class="mt-0.5">{{ __('plus.offers.action_claim') }}</dd></div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2" x-show="detailUntil"><dt class="text-xs text-gray-500 font-semibold">{{ __('plus.offers.expiry') }}</dt><dd class="mt-0.5" x-text="detailUntil"></dd></div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2"><dt class="text-xs text-gray-500 font-semibold">{{ __('plus.offers.redeem_how') }}</dt><dd class="mt-0.5">{{ __('plus.offers.redeem_body') }}</dd></div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2"><dt class="text-xs text-gray-500 font-semibold">{{ __('plus.offers.status') }}</dt>
                        <dd class="mt-0.5" x-text="claimedAlready ? @js(__('plus.offers.status_claimed')) : @js(__('plus.offers.status_available'))"></dd>
                    </div>
                </dl>
                <button type="button" class="mt-4 w-full rounded-xl bg-brand-gold text-brand py-3 font-bold"
                        x-show="!claimedAlready" @click="phase = 'confirm'">{{ __('plus.offers.claim') }}</button>
            </div>
            <div x-show="phase === 'confirm'" x-cloak class="space-y-4">
                <p class="text-sm font-semibold text-gray-900" x-text="detailTitle"></p>
                <p class="text-sm text-gray-600">{{ __('plus.offers.claim_confirm') }}</p>
                @foreach ($offers->merge($claimedOffers ?? collect())->unique('id') as $offer)
                    <form method="post" action="{{ route('site.borrower.plus.offers.claim', $offer) }}" x-show="claimId === {{ $offer->id }}" x-cloak>
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <button type="button" class="rounded-xl bg-white ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="phase = 'detail'">{{ __('plus.learn.prev') }}</button>
                            <button class="rounded-xl bg-brand-gold text-brand py-3 text-sm font-bold">{{ __('plus.offers.confirm_claim') }}</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </x-site.action-panel>
    </div>
</x-site.borrower-layout>

<x-site.borrower-layout :title="brand_title(__('plus.home.offers'))" active="dashboard">
    @php $best = $offers->first(); $rest = $offers->slice(1); @endphp
    <div class="space-y-5" x-data="{ claimOpen: false }">
        <a href="{{ route('site.borrower.plus.home') }}" class="text-sm font-semibold text-brand">← Plus</a>
        <h1 class="text-xl font-bold text-gray-900">{{ __('plus.offers.title') }}</h1>

        @if ($best)
            <div class="rounded-2xl bg-white ring-1 ring-brand/20 p-5">
                <p class="text-[10px] uppercase tracking-[0.16em] text-brand font-bold">{{ __('plus.offers.best') }}</p>
                <p class="text-xs uppercase tracking-widest text-gray-500 mt-2">{{ $best->tier }}</p>
                <h2 class="font-bold text-gray-900 mt-1 text-lg">{{ $best->title }}</h2>
                <p class="text-sm text-gray-600 mt-1">{{ $best->body }}</p>
                @if ($best->ends_at)
                    <p class="text-xs text-gray-500 mt-2">{{ __('plus.offers.until', ['date' => $best->ends_at->format('d M Y')]) }}</p>
                @endif
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="post" action="{{ route('site.borrower.plus.offers.open', $best) }}">
                        @csrf
                        <button class="rounded-xl bg-white ring-1 ring-gray-200 px-4 py-2.5 text-sm font-semibold">{{ __('plus.offers.view') }}</button>
                    </form>
                    @unless ($claimed[$best->id] ?? false)
                        <button type="button" @click="claimOpen = true" class="rounded-xl bg-brand text-white px-4 py-2.5 text-sm font-semibold">{{ __('plus.offers.claim') }}</button>
                    @endunless
                </div>
            </div>
            <x-site.action-panel title="{{ __('plus.offers.claim') }}" open="claimOpen">
                <p class="text-sm text-gray-600">{{ $best->title }}</p>
                <form method="post" action="{{ route('site.borrower.plus.offers.claim', $best) }}" class="mt-4">
                    @csrf
                    <button class="w-full rounded-xl bg-brand text-white py-3 font-semibold">{{ __('plus.offers.claim') }}</button>
                </form>
            </x-site.action-panel>
        @else
            <x-site.empty-state compact icon="🎁" :title="__('plus.offers.empty')" />
        @endif

        @if ($rest->isNotEmpty())
            <p class="text-[10px] uppercase tracking-[0.16em] text-gray-500 font-bold">{{ __('plus.offers.others') }}</p>
            @foreach ($rest as $offer)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                    <p class="text-xs uppercase tracking-widest text-brand">{{ $offer->tier }}</p>
                    <p class="font-semibold mt-1">{{ $offer->title }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $offer->body }}</p>
                    @unless ($claimed[$offer->id] ?? false)
                        <form method="post" action="{{ route('site.borrower.plus.offers.claim', $offer) }}" class="mt-3">
                            @csrf
                            <button class="rounded-xl bg-brand text-white px-4 py-2 text-sm font-semibold">{{ __('plus.offers.claim') }}</button>
                        </form>
                    @endunless
                </div>
            @endforeach
        @endif
    </div>
</x-site.borrower-layout>

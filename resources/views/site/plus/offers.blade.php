<x-site.borrower-layout :title="brand_title(__('plus.home.offers'))" active="dashboard">
    <div class="space-y-3">
        @forelse ($offers as $offer)
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5">
                <p class="text-xs uppercase tracking-widest text-brand">{{ $offer->tier }}</p>
                <p class="font-semibold mt-1">{{ $offer->title }}</p>
                <p class="text-sm text-gray-600 mt-1">{{ $offer->body }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-600">{{ __('plus.offers.empty') }}</p>
        @endforelse
    </div>
</x-site.borrower-layout>

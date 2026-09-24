<x-site.borrower-layout :title="brand_title($asset['title'])" active="marketplace" content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace') }}" data-kf-motion="pop" class="text-xs text-gray-500 hover:text-gray-700">{{ __('borrower.marketplace.back_to_marketplace') }}</a>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            @include('site.marketplace._photo-slider', [
                'photos' => $asset['photos'] ?? [],
                'category' => $asset['category'] ?? 'other',
                'zoom' => true,
                'share' => ! empty($asset['id']) ? 'kf-mp-'.$asset['id'] : null,
            ])
        </div>

        <div>
            <p class="text-xs uppercase tracking-widest text-brand font-bold">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
            <h1 class="text-2xl font-bold mt-1 text-gray-900">{{ $asset['title'] }}</h1>
            @if (! empty($asset['vendor']))
                <p class="text-sm text-gray-700 mt-2">
                    {{ __('borrower.marketplace.supplier') }}: <span class="font-semibold text-gray-900">{{ $asset['vendor'] }}</span>
                    @if (! empty($asset['supplier_region']))
                        <span class="text-gray-600">· {{ $asset['supplier_region'] }}</span>
                    @endif
                </p>
            @endif
            <p class="text-sm text-gray-800 mt-4 leading-relaxed">{{ $asset['description'] }}</p>

            @php
                $maxTenure = max(1, (int) ($asset['max_tenure_months'] ?? 12));
                $price = (float) ($asset['asset_value'] ?? 0);
                $lending = app(\App\Services\AssetLendingService::class);
                $quoteByTenure = [];
                for ($months = 1; $months <= min(12, $maxTenure); $months++) {
                    $quote = $lending->pricingQuoteFromAssetPrice($price, $months);
                    $quoteByTenure[$months] = [
                        'deposit' => $quote['deposit_amount'],
                        'financed' => $quote['financed_amount'],
                        'installment' => $quote['installment'],
                    ];
                }
            @endphp
            <div class="space-y-4 mt-6"
                 x-data="{
                    tenure: null,
                    submitting: false,
                    quotes: @js($quoteByTenure),
                    money(value) { return new Intl.NumberFormat('en-TZ', { style: 'currency', currency: 'TZS', maximumFractionDigits: 0 }).format(value || 0); }
                 }"
                 x-init="if (new URLSearchParams(window.location.search).get('apply') === '1' || window.location.hash === '#apply') { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                        <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.asset_value') }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1 tabular-nums">{{ format_money($asset['asset_value'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                        <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.deposit') }}</p>
                        <p class="text-lg font-bold text-brand mt-1 tabular-nums">{{ format_money($asset['deposit'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                        <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.loan_amount') }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1 tabular-nums">{{ format_money($asset['remaining_loan'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4">
                        <p class="text-[11px] uppercase text-brand font-semibold">Choose duration</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @for ($months = 1; $months <= min(12, $maxTenure); $months++)
                                <button type="button" @click="tenure = {{ $months }}"
                                        :class="tenure === {{ $months }} ? 'bg-brand text-white' : 'bg-white text-gray-700 ring-1 ring-gray-200'"
                                        class="min-w-[2.25rem] px-2 py-1 rounded-lg text-xs font-bold">{{ $months }}</button>
                            @endfor
                        </div>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200 col-span-2" x-show="tenure" x-cloak>
                        <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">Estimated repayment</p>
                        <p class="text-lg font-bold text-gray-900 mt-1 tabular-nums" x-text="money(quotes[tenure]?.installment)"></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3" id="apply">
                    <form method="POST" action="{{ route('site.borrower.marketplace.apply', $asset['id']) }}" @submit="submitting = true">
                        @csrf
                        <input type="hidden" name="tenure_months" :value="tenure || ''">
                        <button type="submit" :disabled="submitting"
                                class="bg-brand-gold hover:brightness-95 disabled:opacity-70 text-brand font-semibold px-6 py-3 rounded-xl text-sm shadow-sm">
                            <span x-show="!submitting">{{ __('borrower.marketplace.apply_asset') }}</span>
                            <span x-show="submitting" x-cloak>{{ __('borrower.marketplace.apply_asset') }}…</span>
                        </button>
                    </form>
                @if ($reservation)
                    <a href="{{ route('site.borrower.marketplace.reserve', $asset['id']) }}" class="inline-flex items-center text-sm font-semibold text-emerald-700">
                        {{ __('borrower.marketplace.continue_application') }} →
                    </a>
                @endif
            </div>
        </div>
    </div>

    @include('site.marketplace._related-assets', ['assets' => $relatedAssets ?? collect(), 'authenticated' => true])

</x-site.borrower-layout>

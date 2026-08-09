<x-site.borrower-layout :title="brand_title($asset['title'])" active="marketplace" content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace') }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('borrower.marketplace.back_to_marketplace') }}</a>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            @include('site.marketplace._photo-slider', ['photos' => $asset['photos'] ?? [], 'category' => $asset['category'] ?? 'other', 'zoom' => true])
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

            <div class="grid grid-cols-2 gap-3 mt-6">
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.asset_value') }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ format_money($asset['asset_value'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.deposit') }}</p>
                    <p class="text-lg font-bold text-brand mt-1">{{ format_money($asset['deposit']) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.loan_amount') }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ format_money($asset['remaining_loan'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-200">
                    <p class="text-[11px] uppercase tracking-widest text-gray-700 font-semibold">{{ __('borrower.marketplace.weekly_installment') }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ format_money($asset['weekly_installment']) }}</p>
                </div>
                @if (! empty($asset['max_tenure_months']))
                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 col-span-2">
                        <p class="text-[11px] uppercase text-brand font-semibold">{{ __('borrower.marketplace.max_tenure') }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</p>
                        <p class="text-xs text-gray-700 mt-1">{{ __('borrower.marketplace.tenure_adjust_hint') }}</p>
                    </div>
                @endif
            </div>

    <p class="mt-4 text-xs text-gray-700">{{ config('asset_marketplace.ownership_note') }}</p>
    <p class="mt-2 text-xs text-gray-700">{{ __('borrower.marketplace.deposit_first_hint') }}</p>

            <div class="mt-6 flex flex-wrap gap-3" id="apply"
                 x-data
                 x-init="if (new URLSearchParams(window.location.search).get('apply') === '1' || window.location.hash === '#apply') { $el.scrollIntoView({ behavior: 'smooth', block: 'center' }); $el.classList.add('ring-2','ring-brand-gold','ring-offset-2','rounded-xl','p-1'); }">
                <form method="POST" action="{{ route('site.borrower.marketplace.apply', $asset['id']) }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
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

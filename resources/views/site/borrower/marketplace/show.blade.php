<x-site.borrower-layout :title="brand_title($asset['title'])" active="marketplace">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace') }}" class="text-xs text-gray-500 hover:text-gray-700">← Back to marketplace</a>
    </div>

    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            @include('site.marketplace._photo-slider', ['photos' => $asset['photos'] ?? [], 'category' => $asset['category'] ?? 'other'])
        </div>

        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
            <h1 class="text-2xl font-bold mt-1">{{ $asset['title'] }}</h1>
            @if (! empty($asset['vendor']))
                <p class="text-sm text-gray-500 mt-2">
                    {{ __('borrower.marketplace.supplier') }}: {{ $asset['vendor'] }}
                    @if (! empty($asset['supplier_region']))
                        <span class="text-gray-400">· {{ $asset['supplier_region'] }}</span>
                    @endif
                </p>
            @endif
            <p class="text-sm text-gray-600 mt-4">{{ $asset['description'] }}</p>

            <div class="grid grid-cols-2 gap-3 mt-6">
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.asset_value') }}</p>
                    <p class="text-lg font-bold">{{ format_money($asset['asset_value'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.deposit') }}</p>
                    <p class="text-lg font-bold">{{ format_money($asset['deposit']) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.loan_amount') }}</p>
                    <p class="text-lg font-bold">{{ format_money($asset['remaining_loan'] ?? 0) }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.weekly_installment') }}</p>
                    <p class="text-lg font-bold">{{ format_money($asset['weekly_installment']) }}</p>
                </div>
                @if (! empty($asset['max_tenure_months']))
                    <div class="rounded-xl bg-gray-50 p-4">
                        <p class="text-[10px] uppercase text-gray-400">{{ __('borrower.marketplace.max_tenure') }}</p>
                        <p class="text-lg font-bold">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</p>
                    </div>
                @endif
            </div>

            <p class="mt-4 text-xs text-gray-500">{{ config('asset_marketplace.ownership_note') }}</p>

            <div class="mt-6 flex flex-wrap gap-3" id="apply">
                <form method="POST" action="{{ route('site.borrower.marketplace.apply', $asset['id']) }}" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <button type="submit" :disabled="submitting"
                            class="bg-amber-500 hover:bg-amber-400 disabled:opacity-70 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
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

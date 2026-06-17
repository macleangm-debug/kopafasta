<x-site.borrower-layout :title="brand_title($asset['title'])" active="marketplace">

    <div class="mb-4">
        <a href="{{ route('site.borrower.marketplace') }}" class="text-xs text-gray-500 hover:text-gray-700">← Back to marketplace</a>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <div>
            @if (! empty($asset['photos']))
                <div class="space-y-3">
                    @foreach ($asset['photos'] as $photo)
                        <img src="{{ Storage::url($photo) }}" alt="" class="w-full rounded-2xl aspect-[4/3] object-cover bg-slate-100">
                    @endforeach
                </div>
            @else
                <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-6xl">
                    @switch($asset['category'])
                        @case('vehicles') 🚗 @break
                        @case('motorcycles') 🏍️ @break
                        @case('equipment') 🧰 @break
                        @default 🏭
                    @endswitch
                </div>
            @endif
        </div>

        <div>
            <p class="text-xs uppercase tracking-widest text-amber-600">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
            <h1 class="text-2xl font-bold mt-1">{{ $asset['title'] }}</h1>
            @if (! empty($asset['vendor']))
                <p class="text-sm text-gray-500 mt-2">{{ __('borrower.marketplace.supplier') }}: {{ $asset['vendor'] }}</p>
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

            @include('site.marketplace._deposit-breakdown', ['asset' => $asset])

            <p class="mt-4 text-xs text-gray-500">{{ config('asset_marketplace.ownership_note') }}</p>

            <div class="mt-6 flex flex-wrap gap-3" id="apply">
                <form method="POST" action="{{ route('site.borrower.marketplace.apply', $asset['id']) }}">
                    @csrf
                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm">
                        {{ __('borrower.marketplace.apply_asset') }}
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

</x-site.borrower-layout>

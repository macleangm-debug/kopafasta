<x-site.layout :title="brand_title($asset['title'])">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <a href="{{ route('site.marketplace') }}" class="text-xs text-gray-500 hover:text-gray-700">{{ __('borrower.marketplace.back_to_marketplace') }}</a>

        <div class="grid lg:grid-cols-2 gap-8 mt-4">
            <div>
                @if (! empty($asset['photos']))
                    @foreach ($asset['photos'] as $photo)
                        <img src="{{ Storage::url($photo) }}" alt="" class="w-full rounded-2xl aspect-[4/3] object-cover bg-slate-100 mb-3">
                    @endforeach
                @else
                    <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-6xl">🚗</div>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase tracking-widest text-amber-600">{{ config('asset_marketplace.categories.'.$asset['category']) }}</p>
                <h1 class="text-3xl font-bold mt-1">{{ $asset['title'] }}</h1>
                @if (! empty($asset['vendor']))
                    <p class="text-sm text-gray-500 mt-2">{{ __('borrower.marketplace.supplier') }}: {{ $asset['vendor'] }}</p>
                @endif
                @if (! empty($asset['description']))
                    <p class="text-sm text-gray-600 mt-4">{{ $asset['description'] }}</p>
                @endif

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
                </div>

                @include('site.marketplace._deposit-breakdown', ['asset' => $asset])

                <div class="mt-8 rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5">
                    <p class="text-sm text-gray-700 mb-4">{{ __('borrower.marketplace.public_apply_hint') }}</p>
                    <a href="{{ $loginUrl }}" class="inline-flex bg-gray-900 hover:bg-gray-800 text-white font-semibold px-6 py-3 rounded-full text-sm">
                        {{ __('borrower.marketplace.public_apply_login') }}
                    </a>
                    <a href="{{ route('site.register.borrower') }}" class="inline-flex ml-3 text-sm font-semibold text-amber-800 hover:underline">{{ __('borrower.marketplace.public_apply_register') }}</a>
                </div>
            </div>
        </div>
    </div>
</x-site.layout>

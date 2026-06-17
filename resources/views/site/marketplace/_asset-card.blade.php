@props([
    'asset',
    'categories' => [],
    'showUrl',
    'applyUrl' => null,
    'authenticated' => false,
])

<article class="bg-white rounded-2xl border border-gray-200 overflow-hidden flex flex-col shadow-sm hover:shadow-md transition-shadow">
    @if (! empty($asset['photos'][0]))
        <img src="{{ Storage::url($asset['photos'][0]) }}" alt="" class="aspect-[4/3] object-cover bg-slate-100">
    @else
        <div class="aspect-[4/3] bg-gradient-to-br from-slate-100 to-slate-200 grid place-items-center text-4xl">
            {{ marketplace_category_emoji($asset['category'] ?? '') }}
        </div>
    @endif
    <div class="p-5 flex-1 flex flex-col">
        <p class="text-xs uppercase tracking-widest text-gray-400">{{ $categories[$asset['category']] ?? $asset['category'] }}</p>
        <h2 class="font-semibold text-gray-900 mt-1">{{ $asset['title'] }}</h2>
        @if (! empty($asset['vendor']))
            <p class="text-xs text-gray-500 mt-1">{{ $asset['vendor'] }}</p>
        @endif
        <dl class="mt-4 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.asset_value') }}</dt><dd class="font-semibold">{{ format_money($asset['asset_value'] ?? 0) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt><dd class="font-semibold">{{ format_money($asset['deposit']) }}</dd></div>
            @if ($authenticated)
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.loan_amount') }}</dt><dd class="font-semibold">{{ format_money($asset['remaining_loan'] ?? 0) }}</dd></div>
            @endif
            <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt><dd class="font-semibold">{{ format_money($asset['weekly_installment']) }}</dd></div>
            @if (! empty($asset['max_tenure_months']))
                <div class="flex justify-between"><dt class="text-gray-500">{{ __('borrower.marketplace.max_tenure') }}</dt><dd class="font-semibold">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</dd></div>
            @endif
        </dl>
        @include('site.marketplace._deposit-breakdown', ['asset' => $asset])
        <div class="mt-5 flex flex-wrap gap-2">
            <a href="{{ $showUrl }}" class="text-sm font-semibold text-amber-700 hover:underline">
                {{ $authenticated ? 'View asset' : 'View details →' }}
            </a>
            @if ($applyUrl)
                <a href="{{ $applyUrl }}" class="text-sm font-semibold text-gray-700 hover:underline">{{ __('borrower.marketplace.apply_asset') }}</a>
            @endif
        </div>
    </div>
</article>

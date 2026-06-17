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
        <h2 class="font-semibold text-gray-900 leading-snug">{{ $asset['title'] }}</h2>
        <dl class="mt-4 space-y-2 text-sm flex-1">
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt>
                <dd class="font-semibold text-gray-900">{{ format_money($asset['deposit']) }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt>
                <dd class="font-semibold text-gray-900">{{ format_money($asset['weekly_installment']) }}</dd>
            </div>
            @if (! empty($asset['max_tenure_months']))
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('borrower.marketplace.max_tenure') }}</dt>
                    <dd class="font-semibold text-gray-900">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</dd>
                </div>
            @endif
        </dl>
        <div class="mt-5">
            <a href="{{ $showUrl }}" class="inline-flex text-sm font-semibold text-amber-700 hover:underline">
                {{ __('borrower.marketplace.view_details') }}
            </a>
        </div>
    </div>
</article>

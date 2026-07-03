@props([
    'asset',
    'categories' => [],
    'showUrl',
    'applyUrl' => null,
    'authenticated' => false,
])

<article class="glass-card overflow-hidden flex flex-col h-full hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] hover:-translate-y-1 transition-all duration-300 group">
    <a href="{{ $showUrl }}" class="block relative overflow-hidden">
        @if (! empty($asset['photos'][0]))
            <img src="{{ Storage::url($asset['photos'][0]) }}" alt="{{ $asset['title'] }}" class="aspect-[4/3] object-cover bg-slate-100 group-hover:scale-105 transition-transform duration-500">
        @else
            <div class="aspect-[4/3] bg-gradient-to-br from-brand-muted to-brand/10 grid place-items-center text-5xl">
                {{ marketplace_category_emoji($asset['category'] ?? '') }}
            </div>
        @endif
        <span class="absolute top-3 left-3 rounded-full bg-white/90 backdrop-blur px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand">
            {{ $categories[$asset['category'] ?? ''] ?? ($asset['category'] ?? '') }}
        </span>
    </a>
    <div class="p-5 flex-1 flex flex-col">
        <a href="{{ $showUrl }}" class="font-bold text-lg text-gray-900 leading-snug group-hover:text-brand transition">{{ $asset['title'] }}</a>
        @if (! empty($asset['vendor']))
            <p class="text-xs text-gray-500 mt-1">
                {{ $asset['vendor'] }}
                @if (! empty($asset['supplier_region']))
                    <span class="text-gray-400">· {{ $asset['supplier_region'] }}</span>
                @endif
            </p>
        @endif
        <dl class="mt-4 space-y-2.5 text-sm flex-1">
            <div class="flex justify-between gap-3 py-2 border-b border-gray-100/80">
                <dt class="text-gray-500">{{ __('borrower.marketplace.deposit') }}</dt>
                <dd class="font-bold text-brand tabular-nums">{{ format_money($asset['deposit']) }}</dd>
            </div>
            <div class="flex justify-between gap-3 py-2 border-b border-gray-100/80">
                <dt class="text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums">{{ format_money($asset['weekly_installment']) }}</dd>
            </div>
            @if (! empty($asset['max_tenure_months']))
                <div class="flex justify-between gap-3 py-2">
                    <dt class="text-gray-500">{{ __('borrower.marketplace.max_tenure') }}</dt>
                    <dd class="font-semibold text-gray-900">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</dd>
                </div>
            @endif
        </dl>
        <a href="{{ $showUrl }}" class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-brand/5 hover:bg-brand text-brand hover:text-white text-sm font-semibold px-4 py-3 transition-all">
            {{ __('borrower.marketplace.view_details') }}
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
        </a>
    </div>
</article>

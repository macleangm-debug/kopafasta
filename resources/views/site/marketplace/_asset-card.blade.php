@props([
    'asset',
    'categories' => [],
    'showUrl',
    'applyUrl' => null,
    'authenticated' => false,
])

<article class="glass-card overflow-hidden flex flex-col h-full hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] hover:-translate-y-0.5 transition-all duration-300 group">
    <a href="{{ $showUrl }}" class="block relative overflow-hidden bg-slate-50" x-data="{ imgLoaded: {{ empty($asset['photos'][0]) ? 'true' : 'false' }} }">
        @if (! empty($asset['photos'][0]))
            <div x-show="!imgLoaded" class="absolute inset-0 skeleton z-10"></div>
            <img src="{{ Storage::url($asset['photos'][0]) }}" alt="{{ $asset['title'] }}" loading="lazy"
                 @load="imgLoaded = true"
                 class="aspect-[4/3] w-full object-cover group-hover:scale-[1.03] transition-all duration-500"
                 :class="imgLoaded ? 'opacity-100' : 'opacity-0'">
        @else
            <div class="aspect-[4/3] bg-gradient-to-br from-brand-muted to-brand/10 grid place-items-center text-5xl">
                {{ marketplace_category_emoji($asset['category'] ?? '') }}
            </div>
        @endif
        <span class="absolute top-3 left-3 rounded-full bg-white/95 backdrop-blur px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-brand shadow-sm">
            {{ $categories[$asset['category'] ?? ''] ?? ($asset['category'] ?? '') }}
        </span>
        @if (! empty($asset['asset_value']))
            <span class="absolute bottom-3 right-3 rounded-lg bg-brand/90 backdrop-blur text-white text-xs font-bold px-2.5 py-1 tabular-nums shadow-sm">
                {{ format_money($asset['asset_value'], false, 0) }}
            </span>
        @endif
    </a>
    <div class="p-4 flex-1 flex flex-col gap-3">
        <div class="min-w-0">
            <a href="{{ $showUrl }}" class="font-bold text-base text-gray-900 leading-snug line-clamp-2 group-hover:text-brand transition">{{ $asset['title'] }}</a>
            @if (! empty($asset['vendor']))
                <p class="text-xs text-gray-500 mt-1 truncate">
                    {{ $asset['vendor'] }}
                    @if (! empty($asset['supplier_region']))
                        <span class="text-gray-400">· {{ $asset['supplier_region'] }}</span>
                    @endif
                </p>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-xl bg-brand-muted/60 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('borrower.marketplace.deposit') }}</p>
                <p class="font-bold text-brand tabular-nums mt-0.5">{{ format_money($asset['deposit'], false, 0) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-3 py-2.5 ring-1 ring-gray-100">
                <p class="text-[10px] uppercase tracking-wide text-gray-500">{{ __('borrower.marketplace.weekly_installment') }}</p>
                <p class="font-bold text-gray-900 tabular-nums mt-0.5">{{ format_money($asset['weekly_installment'], false, 0) }}</p>
            </div>
        </div>

        @if (! empty($asset['max_tenure_months']))
            <p class="text-[11px] text-gray-500">
                {{ __('borrower.marketplace.max_tenure') }}: <span class="font-semibold text-gray-800">{{ $asset['max_tenure_months'] }} {{ __('borrower.apply.quote.months') }}</span>
            </p>
        @endif

        <a href="{{ $showUrl }}" class="mt-auto inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-2.5 transition-all">
            {{ __('borrower.marketplace.view_details') }}
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
        </a>
    </div>
</article>

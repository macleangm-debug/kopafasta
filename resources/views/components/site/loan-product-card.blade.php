@props(['product'])

@php
    $theme = loan_product_theme($product->code);
    $rateService = app(\App\Services\DisplayedRateService::class);
    $rateLabel = $rateService->formatBorrowerRateRange($product);
    $productName = $product->localizedName();
@endphp

<div class="snap-start shrink-0 w-[min(85vw,320px)] glass-card overflow-hidden flex flex-col hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] transition-shadow">
    <div class="px-5 py-4 bg-gradient-to-br from-brand/10 via-brand-muted/60 to-transparent border-b border-white/60">
        <div class="flex items-start gap-3">
            <span class="size-11 rounded-xl bg-white/90 shadow-sm grid place-items-center text-2xl ring-1 ring-brand/10">{{ $theme['icon'] ?? '💼' }}</span>
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-mono font-semibold uppercase tracking-widest text-brand/60">{{ $product->code }}</p>
                <h3 class="text-lg font-extrabold text-brand leading-tight">{{ $productName }}</h3>
            </div>
        </div>
    </div>
    <button type="button" @click="open = open === {{ $product->id }} ? null : {{ $product->id }}"
            class="text-left p-5 flex-1 hover:bg-brand-muted/20 transition">
        <p class="text-sm text-gray-600 line-clamp-3">{{ $product->description ?: __('borrower.dashboard.browse_products') }}</p>
        <dl class="mt-4 space-y-2 text-sm">
            <div>
                <dt class="text-xs text-gray-500">{{ __('borrower.apply.details.monthly_rate') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums">{{ $rateLabel }}</dd>
            </div>
            <div class="flex justify-between gap-2">
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.products.amount') }}</dt>
                    <dd class="font-semibold text-gray-900 tabular-nums text-xs">{{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}</dd>
                </div>
                <div class="text-right">
                    <dt class="text-xs text-gray-500">{{ __('site.products.tenure') }}</dt>
                    <dd class="font-semibold text-gray-900 tabular-nums">{{ $product->tenure_max_months }} {{ __('borrower.apply.details.months') }}</dd>
                </div>
            </div>
        </dl>
    </button>
    <div x-show="open === {{ $product->id }}" x-transition x-cloak class="border-t border-gray-100/80 px-5 pb-5 pt-4 bg-white/50">
        <a href="{{ route('site.borrower.apply', ['product' => $product->id]) }}"
           class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-bold px-5 py-3 rounded-xl text-sm transition shadow-sm">
            {{ __('borrower.new_application') }} →
        </a>
    </div>
</div>

@props(['product'])

@php
    $theme = loan_product_theme($product->code);
    $rateService = app(\App\Services\DisplayedRateService::class);
    $rateLabel = $rateService->formatBorrowerRateRange($product);
    $productName = $product->localizedName();
    $isMarketplace = is_marketplace_loan_product($product->code);
    $ctaUrl = $isMarketplace ? route('site.borrower.marketplace') : route('site.product', $product->code);
    $ctaLabel = $isMarketplace ? __('borrower.nav.marketplace') : __('site.products.learn_more');
@endphp

<div class="snap-start shrink-0 w-[min(85vw,320px)] glass-card overflow-hidden flex flex-col hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] transition-shadow">
    <div class="relative">
        <x-site.product-illustration :code="$product->code" size="sm" class="!rounded-none !size-auto w-full !aspect-[16/9] !max-w-none" />
        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/50 to-transparent p-4 pt-10">
            <p class="text-[10px] font-mono font-semibold uppercase tracking-widest text-white/70">{{ $product->code }}</p>
            <h3 class="text-lg font-extrabold text-white leading-tight">{{ $productName }}</h3>
        </div>
    </div>
    <button type="button" @click="open = open === {{ $product->id }} ? null : {{ $product->id }}"
            class="text-left p-5 flex-1 hover:bg-brand-muted/20 transition">
        <p class="text-sm text-gray-600 line-clamp-2">
            @if ($isMarketplace)
                {{ __('borrower.marketplace.subtitle') }}
            @else
                {{ $theme['label'] ?? ($product->description ?: __('borrower.dashboard.browse_products')) }}
            @endif
        </p>
        @unless ($isMarketplace)
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
        @endunless
    </button>
    <div x-show="open === {{ $product->id }}" x-transition x-cloak class="border-t border-gray-100/80 px-5 pb-5 pt-4 bg-white/50">
        <a href="{{ $ctaUrl }}"
           class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-bold px-5 py-3 rounded-xl text-sm transition shadow-sm">
            {{ $ctaLabel }} →
        </a>
    </div>
</div>

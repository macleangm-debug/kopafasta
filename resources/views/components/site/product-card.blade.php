@props([
    'product',
    'rateLabel' => null,
])

@php
    $rateLabel = $rateLabel ?? app(\App\Services\DisplayedRateService::class)->formatBorrowerRateRange($product);
    $isActive = $product->status === 'active';
    $statusLabel = $isActive ? __('site.products.status_active') : __('site.products.status_coming_soon');
    $theme = config('loan_product_themes.'.$product->code, config('loan_product_themes.default'));
    $productName = $product->localizedName();
@endphp

<article class="glass-card overflow-hidden hover:shadow-[0_16px_48px_rgba(0,77,64,0.14)] hover:-translate-y-0.5 transition-all duration-300 flex flex-col h-full group">
    <div class="p-4 pb-0">
        <x-site.product-illustration :code="$product->code" size="card" />
    </div>
    <div class="p-5 pt-4 flex flex-col flex-1">
        <div class="flex items-start justify-between gap-2 mb-2">
            <p class="text-[11px] font-mono font-semibold uppercase tracking-widest text-brand/60">{{ $product->code }}</p>
            <span class="inline-flex items-center rounded-full text-[10px] font-semibold px-2.5 py-1 {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                {{ $statusLabel }}
            </span>
        </div>
        <h3 class="text-xl font-extrabold text-brand leading-tight tracking-tight group-hover:text-brand-light transition-colors">
            {{ $productName }}
        </h3>
        <p class="mt-2 text-sm text-gray-600 line-clamp-2 leading-relaxed flex-1">{{ loan_product_card_description($product) }}</p>

        <div class="mt-4 space-y-2 text-xs">
            <div class="flex justify-between gap-2 py-2 border-b border-gray-100/80">
                <span class="text-gray-500">{{ __('site.products.monthly_rate') }}</span>
                <span class="font-semibold text-gray-900">{{ $rateLabel }} / mo</span>
            </div>
            <div class="flex justify-between gap-2 py-2">
                <span class="text-gray-500">{{ __('site.products.amount') }}</span>
                <span class="font-semibold text-gray-900 tabular-nums">{{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}</span>
            </div>
        </div>

        <a href="{{ route('site.product', $product->code) }}"
           class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-bold px-4 py-3 transition-all duration-300 shadow-sm">
            {{ __('site.products.learn_more') }}
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
        </a>
    </div>
</article>

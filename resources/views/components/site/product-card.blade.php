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

<article class="glass-card p-6 hover:shadow-[0_12px_40px_rgba(0,77,64,0.12)] hover:-translate-y-0.5 transition-all duration-300 flex flex-col h-full group">
    <div class="flex items-start justify-between gap-3 mb-4">
        <span class="size-12 rounded-2xl bg-brand-muted/80 grid place-items-center text-2xl shadow-inner">{{ $theme['icon'] ?? '💼' }}</span>
        <span class="inline-flex items-center rounded-full text-[10px] font-semibold px-2.5 py-1 {{ $isActive ? 'bg-emerald-100/90 text-emerald-800' : 'bg-slate-100/90 text-slate-600' }}">
            {{ $statusLabel }}
        </span>
    </div>

    <p class="text-[11px] font-mono font-semibold text-brand/70">{{ $product->code }}</p>
    <h3 class="mt-1 text-xl font-bold text-gray-900 leading-tight group-hover:text-brand transition-colors">{{ $productName }}</h3>
    <p class="mt-3 text-sm text-gray-600 line-clamp-3 leading-relaxed flex-1">{{ $product->description }}</p>

    <div class="mt-5 space-y-2.5 text-xs">
        <div class="flex justify-between gap-2 py-2 border-b border-gray-100/80">
            <span class="text-gray-500">{{ __('site.products.monthly_rate') }}</span>
            <span class="font-semibold text-gray-900">{{ $rateLabel }} / mo</span>
        </div>
        <div class="flex justify-between gap-2 py-2 border-b border-gray-100/80">
            <span class="text-gray-500">{{ __('site.products.amount') }}</span>
            <span class="font-semibold text-gray-900 tabular-nums">{{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}</span>
        </div>
    </div>

    <span class="inline-block mt-4 rounded-full bg-brand-gold/25 text-brand px-3 py-1 text-xs font-semibold w-fit">
        {{ __('site.products.up_to', ['amount' => format_money($product->max_amount, false, 0)]) }}
    </span>

    <a href="{{ route('site.product', $product->code) }}"
       class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-brand/5 hover:bg-brand text-brand hover:text-white text-sm font-semibold px-4 py-3 transition-all duration-300">
        {{ __('site.products.learn_more') }}
        <svg class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
    </a>
</article>

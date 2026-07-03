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

<article class="glass-card overflow-hidden hover:shadow-[0_16px_48px_rgba(0,77,64,0.14)] hover:-translate-y-1 transition-all duration-300 flex flex-col h-full group">
    <div class="relative px-6 pt-6 pb-4 bg-gradient-to-br from-brand/5 via-brand-muted/40 to-transparent border-b border-white/60">
        <div class="flex items-start justify-between gap-3">
            <span class="size-12 rounded-2xl bg-white/90 shadow-sm grid place-items-center text-2xl ring-1 ring-brand/10">{{ $theme['icon'] ?? '💼' }}</span>
            <span class="inline-flex items-center rounded-full text-[10px] font-semibold px-2.5 py-1 {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                {{ $statusLabel }}
            </span>
        </div>
        <p class="mt-4 text-[11px] font-mono font-semibold uppercase tracking-widest text-brand/60">{{ $product->code }}</p>
        <h3 class="mt-1 text-2xl sm:text-[1.65rem] font-extrabold text-brand leading-tight tracking-tight group-hover:text-brand-light transition-colors">
            {{ $productName }}
        </h3>
    </div>

    <div class="p-6 flex flex-col flex-1">
        <p class="text-sm text-gray-600 line-clamp-3 leading-relaxed flex-1">{{ $product->description }}</p>

        <div class="mt-5 space-y-2.5 text-xs">
            <div class="flex justify-between gap-2 py-2 border-b border-gray-100/80">
                <span class="text-gray-500">{{ __('site.products.monthly_rate') }}</span>
                <span class="font-semibold text-gray-900">{{ $rateLabel }} / mo</span>
            </div>
            <div class="flex justify-between gap-2 py-2">
                <span class="text-gray-500">{{ __('site.products.amount') }}</span>
                <span class="font-semibold text-gray-900 tabular-nums">{{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}</span>
            </div>
        </div>

        <span class="inline-block mt-4 rounded-full bg-brand-gold/30 text-brand px-3 py-1 text-xs font-bold w-fit">
            {{ __('site.products.up_to', ['amount' => format_money($product->max_amount, false, 0)]) }}
        </span>

        <a href="{{ route('site.product', $product->code) }}"
           class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-bold px-4 py-3.5 transition-all duration-300 shadow-sm">
            {{ __('site.products.learn_more') }}
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
        </a>
    </div>
</article>

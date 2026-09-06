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
    $hasTenure = filled($product->tenure_max_months ?? null);
@endphp

<article class="glass-card overflow-hidden hover:shadow-[0_16px_48px_rgba(0,77,64,0.14)] hover:-translate-y-0.5 transition-all duration-300 flex flex-col h-full group">
    <div class="p-3 pb-0">
        <x-site.product-illustration :code="$product->code" size="card" class="!aspect-[2/1]" />
    </div>
    <div class="px-4 py-3.5 flex flex-col flex-1">
        <div class="flex items-center justify-between gap-2 shrink-0">
            <p class="text-[11px] font-mono font-semibold uppercase tracking-widest text-brand/60">{{ $product->code }}</p>
            <span class="inline-flex items-center rounded-full text-[10px] font-semibold px-2 py-0.5 {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                {{ $statusLabel }}
            </span>
        </div>

        <h3 class="mt-1.5 text-lg font-extrabold text-brand leading-snug tracking-tight line-clamp-2 min-h-[2.75rem] group-hover:text-brand-light transition-colors">
            {{ $productName }}
        </h3>
        <p class="mt-0.5 text-sm text-gray-600 line-clamp-2 min-h-[2.5rem] leading-snug">{{ loan_product_card_description($product) }}</p>

        <dl class="mt-2.5 grid grid-cols-1 gap-0 text-xs">
            <div class="flex justify-between gap-2 py-1 border-b border-gray-100/80">
                <dt class="text-gray-500">{{ __('site.products.amount') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums text-right">{{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}</dd>
            </div>
            <div class="flex justify-between gap-2 py-1 {{ $hasTenure ? 'border-b border-gray-100/80' : '' }}">
                <dt class="text-gray-500">{{ loan_product_rate_field_label($product) }}</dt>
                <dd class="font-semibold text-gray-900">{{ $rateLabel }} / mo</dd>
            </div>
            @if ($hasTenure)
                <div class="flex justify-between gap-2 py-1">
                    <dt class="text-gray-500">{{ __('site.products.tenure') }}</dt>
                    <dd class="font-semibold text-gray-900 tabular-nums">{{ $product->tenure_max_months }} {{ __('borrower.apply.details.months') }}</dd>
                </div>
            @endif
        </dl>

        <a href="{{ route('site.product', $product->code) }}"
           class="mt-auto pt-3 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-bold px-4 py-2.5 transition-all duration-300 shadow-sm">
            {{ __('site.products.learn_more') }}
            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
        </a>
    </div>
</article>

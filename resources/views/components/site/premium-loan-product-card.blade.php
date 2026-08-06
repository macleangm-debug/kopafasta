@props(['product', 'customer' => null])

@php
    $theme = loan_product_theme($product->code);
    $rateService = app(\App\Services\DisplayedRateService::class);
    $rateLabel = $rateService->formatBorrowerRateRange($product);
    $productName = $product->localizedName();
    $isMarketplace = is_marketplace_loan_product($product->code);
    $status = $product->status ?? ($product->is_active ? 'active' : 'inactive');
    $isAvailable = $status === 'active' && ($product->is_active ?? true);
    $ctaUrl = match (true) {
        ! $isAvailable => '#',
        $isMarketplace => route('site.borrower.marketplace'),
        default => route('site.borrower.apply', ['product' => $product->id]),
    };
    $ctaLabel = match ($status) {
        'coming_soon' => __('borrower.dashboard.product_coming_soon'),
        'inactive'    => __('borrower.dashboard.product_inactive'),
        default       => $isMarketplace ? __('borrower.nav.marketplace') : __('borrower.loan_products_page.apply_now'),
    };
    $statusBadge = match ($status) {
        'coming_soon' => ['label' => __('borrower.dashboard.product_coming_soon'), 'class' => 'bg-sky-100 text-sky-800 ring-sky-200'],
        'inactive'    => ['label' => __('borrower.dashboard.product_inactive'), 'class' => 'bg-gray-100 text-gray-600 ring-gray-200'],
        default       => ['label' => __('borrower.dashboard.product_active'), 'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
    };
    $description = loan_product_card_description($product);
    $category = (string) ($product->category ?: 'general');
@endphp

<article
    data-product-card
    data-category="{{ $category }}"
    data-search="{{ strtolower($product->code.' '.$productName.' '.$description) }}"
    class="glass-card overflow-hidden flex flex-col h-full hover:shadow-[0_16px_48px_rgba(0,77,64,0.14)] hover:-translate-y-0.5 transition-all duration-300 group {{ ! $isAvailable ? 'opacity-90' : '' }}"
>
    <div class="relative">
        <x-site.product-illustration :code="$product->code" size="card" class="!rounded-none !size-auto w-full !aspect-[16/9] !max-w-none" />
        <div class="absolute top-3 left-3">
            <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2.5 py-1 ring-1 {{ $statusBadge['class'] }}">
                {{ $statusBadge['label'] }}
            </span>
        </div>
        <div class="absolute top-3 right-3">
            <span class="inline-flex text-[10px] font-mono font-semibold uppercase tracking-widest rounded-full px-2 py-1 bg-white/90 text-brand/70 ring-1 ring-white/80">
                {{ $product->code }}
            </span>
        </div>
    </div>

    <div class="p-5 flex flex-col flex-1">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-brand/70">{{ loan_product_type_label($product) }}</p>
        <h3 class="text-xl font-extrabold text-brand leading-tight tracking-tight mt-1 group-hover:text-brand-light transition-colors line-clamp-2 min-h-[2.75rem]" title="{{ $productName }}">
            {{ $productName }}
        </h3>
        <p class="mt-2 text-sm text-gray-600 line-clamp-2 leading-relaxed min-h-[2.5rem]">{{ $description }}</p>

        <dl class="mt-4 space-y-2 text-xs">
            <div class="flex justify-between gap-2 py-2 border-b border-gray-100/80">
                <dt class="text-gray-500">{{ __('borrower.apply.details.monthly_rate') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums">
                    @if ($isMarketplace)
                        @php $assetRate = rtrim(rtrim(format_number((float) config('asset_lending.default_monthly_rate', 0.12) * 100, 1), '0'), '.'); @endphp
                        {{ __('borrower.marketplace.from_rate', ['rate' => $assetRate]) }}
                    @else
                        {{ $rateLabel }}
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-2 py-2 border-b border-gray-100/80">
                <dt class="text-gray-500">{{ __('borrower.loan_products_page.max_amount') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums">{{ format_money($product->max_amount, false, 0) }}</dd>
            </div>
            <div class="flex justify-between gap-2 py-2">
                <dt class="text-gray-500">{{ __('borrower.loan_products_page.max_duration') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums">{{ $product->tenure_max_months }} {{ __('borrower.apply.details.months') }}</dd>
            </div>
        </dl>

        @if ($isAvailable)
            <a href="{{ $ctaUrl }}"
               class="mt-5 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-bold px-4 py-3 transition-all duration-300 shadow-sm">
                {{ $ctaLabel }}
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
        @else
            <p class="mt-5 text-center text-sm text-gray-500 py-2">{{ __('borrower.dashboard.product_unavailable_hint') }}</p>
        @endif
    </div>
</article>

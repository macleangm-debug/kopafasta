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
        <x-site.product-illustration :code="$product->code" size="card" class="!rounded-none !size-auto w-full !aspect-[2/1] !max-w-none" />
        <div class="absolute top-2 left-2">
            <span class="inline-flex text-[10px] font-bold uppercase tracking-wide rounded-full px-2 py-0.5 ring-1 {{ $statusBadge['class'] }}">
                {{ $statusBadge['label'] }}
            </span>
        </div>
        <div class="absolute top-2 right-2">
            <span class="inline-flex text-[10px] font-mono font-semibold uppercase tracking-widest rounded-full px-2 py-0.5 bg-white/90 text-brand/70 ring-1 ring-white/80">
                {{ $product->code }}
            </span>
        </div>
    </div>

    <div class="px-4 py-3.5 flex flex-col flex-1">
        <p class="text-[10px] font-semibold uppercase tracking-widest text-brand/70">{{ loan_product_type_label($product) }}</p>

        <div class="mt-1 flex-1 flex flex-col">
            <h3 class="text-lg font-extrabold text-brand leading-snug tracking-tight group-hover:text-brand-light transition-colors line-clamp-2" title="{{ $productName }}">
                {{ $productName }}
            </h3>
            <p class="mt-0.5 text-sm text-gray-600 line-clamp-2 leading-snug">{{ $description }}</p>

            <dl class="mt-auto pt-2.5 space-y-0 text-xs">
                <div class="flex justify-between gap-2 py-1 border-b border-gray-100/80">
                    <dt class="text-gray-500">{{ loan_product_rate_field_label($product) }}</dt>
                    <dd class="font-semibold text-gray-900 tabular-nums">
                        @if ($isMarketplace)
                            @php $assetRate = rtrim(rtrim(format_number((float) config('asset_lending.default_monthly_rate', 0.12) * 100, 1), '0'), '.'); @endphp
                            {{ __('borrower.marketplace.from_rate', ['rate' => $assetRate]) }}
                        @else
                            {{ $rateLabel }}
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between gap-2 py-1 border-b border-gray-100/80">
                    <dt class="text-gray-500">{{ __('borrower.loan_products_page.max_amount') }}</dt>
                    <dd class="font-semibold text-gray-900 tabular-nums">{{ format_money($product->max_amount, false, 0) }}</dd>
                </div>
                <div class="flex justify-between gap-2 py-1">
                    <dt class="text-gray-500">{{ __('borrower.loan_products_page.max_duration') }}</dt>
                    <dd class="font-semibold text-gray-900 tabular-nums">{{ $product->tenure_max_months }} {{ __('borrower.apply.details.months') }}</dd>
                </div>
            </dl>
        </div>

        @if ($isAvailable)
            <a href="{{ $ctaUrl }}"
               class="mt-3 inline-flex items-center justify-center gap-2 rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-bold px-4 py-2.5 transition-all duration-300 shadow-sm">
                {{ $ctaLabel }}
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
        @else
            <p class="mt-3 text-center text-sm text-gray-500 py-1.5">{{ __('borrower.dashboard.product_unavailable_hint') }}</p>
        @endif
    </div>
</article>

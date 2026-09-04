@props(['product'])

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
        default => route('site.borrower.apply', ['product' => $product->id, 'intent' => 'apply']),
    };
    $ctaLabel = match ($status) {
        'coming_soon' => __('borrower.dashboard.product_coming_soon'),
        'inactive'    => __('borrower.dashboard.product_inactive'),
        default       => $isMarketplace ? __('borrower.nav.marketplace') : __('borrower.dashboard.apply_now'),
    };
    $statusBadge = match ($status) {
        'coming_soon' => ['label' => __('borrower.dashboard.product_coming_soon'), 'class' => 'bg-sky-100 text-sky-800 ring-sky-200'],
        'inactive'    => ['label' => __('borrower.dashboard.product_inactive'), 'class' => 'bg-gray-100 text-gray-600 ring-gray-200'],
        default       => ['label' => __('borrower.dashboard.product_active'), 'class' => 'bg-emerald-100 text-emerald-800 ring-emerald-200'],
    };
    $description = loan_product_card_description($product);
    $assetRate = rtrim(rtrim(format_number((float) config('asset_lending.default_monthly_rate', 0.12) * 100, 1), '0'), '.');
@endphp

<article class="snap-start shrink-0 w-[min(85vw,300px)] self-stretch glass-card overflow-hidden flex flex-col hover:shadow-[0_16px_48px_rgba(0,77,64,0.12)] transition-shadow {{ ! $isAvailable ? 'opacity-90' : '' }}">
    <div class="relative shrink-0">
        <x-site.product-illustration :code="$product->code" :image-path="$product->image_path" size="sm" class="!rounded-none !size-auto w-full !aspect-[2/1] !max-w-none" />
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

    <div class="px-3.5 py-3 flex flex-col flex-1 min-h-0">
        <h3 class="text-base font-extrabold text-brand leading-snug tracking-tight line-clamp-2" title="{{ $productName }}">
            {{ $productName }}
        </h3>
        <p class="mt-0.5 text-xs text-gray-600 line-clamp-2 leading-snug">
            {{ $description }}
        </p>

        <dl class="mt-2.5 space-y-0 text-xs flex-1">
            <div class="flex justify-between gap-2 py-1 border-b border-gray-100/80">
                <dt class="text-gray-500 shrink-0">{{ loan_product_rate_field_label($product) }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums text-right">
                    @if ($isMarketplace)
                        {{ __('borrower.marketplace.from_rate', ['rate' => $assetRate]) }}
                    @else
                        {{ $rateLabel }}
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-2 py-1 border-b border-gray-100/80">
                <dt class="text-gray-500 shrink-0">{{ __('site.products.amount') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums text-right truncate" title="{{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}">
                    @if ($isMarketplace)
                        {{ format_money($product->min_amount ?: $product->max_amount, false, 0) }}+
                    @else
                        {{ format_money($product->min_amount, false, 0) }} – {{ format_money($product->max_amount, false, 0) }}
                    @endif
                </dd>
            </div>
            <div class="flex justify-between gap-2 py-1">
                <dt class="text-gray-500 shrink-0">{{ __('site.products.tenure') }}</dt>
                <dd class="font-semibold text-gray-900 tabular-nums text-right">
                    {{ $product->tenure_max_months }} {{ __('borrower.apply.details.months') }}
                </dd>
            </div>
        </dl>

        <div class="mt-auto pt-2.5">
            @if ($isAvailable)
                <a href="{{ $ctaUrl }}"
                   data-loading="click"
                   class="inline-flex w-full justify-center bg-brand hover:bg-brand-light text-white font-bold px-4 py-2 rounded-xl text-sm transition shadow-sm">
                    {{ $ctaLabel }} →
                </a>
            @else
                <p class="text-center text-xs text-gray-500 py-1.5">{{ __('borrower.dashboard.product_unavailable_hint') }}</p>
            @endif
        </div>
    </div>
</article>

@php
    $p = $presentation;
    $isActive = $p['is_active'];
    $isMarketplaceProduct = is_marketplace_loan_product($product->code);
    $applyUrl = $isMarketplaceProduct
        ? (auth()->check() ? route('site.borrower.marketplace') : route('site.marketplace'))
        : route('site.borrower.apply', ['product' => $product->id, 'intent' => 'apply']);
    $guestApplyUrl = $isMarketplaceProduct
        ? route('site.login', ['redirect' => route('site.marketplace')])
        : route('site.login', ['redirect' => route('site.borrower.apply', ['product' => $product->id, 'intent' => 'apply'])]);
    $cadence = app(\App\Services\GroupLendingService::class)->effectiveRepaymentCadence($product);
    $isMonthlyCadence = $cadence === 'monthly';
    $applicationFee = (float) ($p['fees']['application'] ?? 0);
    $applyHref = $isActive ? (auth()->check() ? $applyUrl : $guestApplyUrl) : null;
    $infoCards = [
        [
            'title' => __('site.product_detail.target_audience'),
            'body' => $p['target_audience'] ?: $p['overview_short'],
            'items' => [],
        ],
        [
            'title' => __('site.product_detail.eligibility_heading'),
            'body' => null,
            'items' => collect($p['eligibility'] ?? [])->take(5)->map(fn ($item) => is_array($item) ? ($item['label'] ?? '') : $item)->filter()->values()->all(),
        ],
        [
            'title' => __('site.product_detail.documents_heading'),
            'body' => null,
            'items' => collect($p['documents'] ?? [])->take(5)->map(fn ($doc) => is_array($doc) ? ($doc['name'] ?? $doc['label'] ?? '') : $doc)->filter()->values()->all(),
        ],
        [
            'title' => __('seo.how_to_apply'),
            'body' => null,
            'items' => collect($p['apply_steps'] ?? [])->take(4)->map(fn ($step) => $step['title'] ?? '')->filter()->values()->all(),
        ],
    ];
@endphp
<x-site.layout
    :title="$productSeo['title']"
    :description="$productSeo['description']"
    :seo="[
        'image' => $productSeo['image'] ?? null,
        'indexable' => $productSeo['indexable'] ?? true,
        'faqs' => $p['faq'] ?? [],
        'breadcrumbs' => [
            ['name' => brand_name(), 'url' => route('site.home')],
            ['name' => __('site.nav.all_products'), 'url' => route('site.products')],
            ['name' => $p['name'], 'url' => route('site.product', $product->code)],
        ],
    ]"
>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
        <a href="{{ route('site.products') }}" class="text-sm text-brand hover:underline inline-flex items-center gap-1">
            ← {{ __('site.nav.all_products') }}
        </a>
    </div>

    <x-site.public-hero
        variant="compact"
        :eyebrow="$p['code'].' · '.($isActive ? __('site.products.status_active') : __('site.products.status_coming_soon'))"
        :title="$p['name']"
        :body="$p['overview_short']"
        :primary-href="$applyHref"
        :primary-label="$isActive ? ($isMarketplaceProduct ? __('site.nav.marketplace') : __('site.products.apply_now')) : null"
        :facts="[
            ['label' => __('site.products.amount'), 'value' => format_money($p['limits']['min_amount'], false, 0).' – '.format_money($p['limits']['max_amount'], false, 0)],
            ['label' => loan_product_rate_field_label($product), 'value' => $p['rate_label']],
            ['label' => __('site.products.tenure'), 'value' => $p['limits']['tenure_min_months'].'–'.$p['limits']['tenure_max_months'].' '.__('borrower.apply.details.months')],
            ['label' => __('site.products.repayment'), 'value' => $p['repayment_frequency_label']],
        ]"
    />

    @unless ($isMarketplaceProduct)
    <x-site.public-section tone="muted" narrow>
        <div class="rounded-[1.5rem] bg-white ring-1 ring-brand/10 shadow-[0_16px_40px_rgba(8,47,39,0.08)] overflow-hidden"
             x-data="productCalculator(@js([
                'min' => $p['limits']['min_amount'],
                'max' => $p['limits']['max_amount'],
                'tmin' => $p['limits']['tenure_min_months'],
                'tmax' => $p['limits']['tenure_max_months'],
                'cadence' => $cadence,
                'quoteUrl' => route('site.product.quote', $product->code),
             ]))">
            <div class="grid lg:grid-cols-2 gap-0">
                <div class="p-5 sm:p-7 space-y-5">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.product_detail.calculator_eyebrow') }}</p>
                        <h2 class="text-xl font-bold text-gray-900 mt-1">{{ __('site.product_detail.calculator') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ __('site.product_detail.calculator_hint') }}</p>
                    </div>
                    <div>
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>{{ __('site.products.amount') }}</span>
                            <span class="font-semibold tabular-nums" x-text="formatMoney(amount)"></span>
                        </div>
                        <input type="range" :min="config.min" :max="config.max" step="50000" x-model.number="amount" class="w-full accent-brand">
                    </div>
                    <div>
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>{{ __('site.products.tenure') }}</span>
                            <span class="font-semibold"><span x-text="tenure"></span> {{ __('borrower.apply.details.months') }}</span>
                        </div>
                        <input type="range" :min="config.tmin" :max="config.tmax" step="1" x-model.number="tenure" class="w-full accent-brand">
                    </div>
                    @if ($applicationFee > 0)
                        <p class="text-xs text-gray-500">{{ __('site.product_detail.application_fee') }}: <span class="font-semibold text-gray-800">{{ format_money($applicationFee) }}</span></p>
                    @endif
                </div>
                <div class="bg-gradient-to-br from-brand via-[#0f6b54] to-[#082f27] text-white p-5 sm:p-7 flex flex-col justify-between gap-5">
                    <div class="space-y-4">
                        <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ __('site.product_detail.calculator') }}</p>
                        <div>
                            <p class="text-xs text-white/70">{{ $isMonthlyCadence ? __('site.product_detail.monthly_payment') : __('site.product_detail.weekly_payment') }}</p>
                            <p class="mt-1 text-3xl font-black tabular-nums" x-text="loading ? '…' : formatMoney(installment)"></p>
                        </div>
                        <div>
                            <p class="text-xs text-white/70">{{ __('site.product_detail.total_repayment') }}</p>
                            <p class="mt-1 text-2xl font-bold tabular-nums" x-text="loading ? '…' : formatMoney(total)"></p>
                        </div>
                        <p class="text-xs text-white/60">{{ __('site.product_detail.calculator_disclaimer') }}</p>
                    </div>
                    @if ($applyHref)
                        <a href="{{ $applyHref }}" class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand font-extrabold px-5 py-3.5">
                            {{ __('site.products.apply_now') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
        @if (! empty($p['fees']['post_approval_lines']))
            <p class="mt-4 text-xs text-gray-500 leading-relaxed">
                {{ __('site.product_detail.fees_heading') }}:
                @foreach ($p['fees']['post_approval_lines'] as $line)
                    <span class="font-medium text-gray-700">{{ $line['name'] ?? '' }}</span>
                    ({{ $line['display'] ?? '' }})@if (! $loop->last), @endif
                @endforeach
                · <a href="{{ route('site.responsible-lending') }}" class="text-brand font-semibold hover:underline">{{ __('site.footer.responsible_lending') }}</a>
            </p>
        @endif
    </x-site.public-section>
    @endunless

    <x-site.public-section>
        <x-site.public-carousel :title="__('site.product_detail.features')" :subtitle="__('site.product_detail.overview_short_hint') ?? null">
            @foreach ($infoCards as $card)
                <div data-public-slide class="snap-start shrink-0 w-[min(100%,calc(100vw-3rem))] sm:w-[280px] lg:w-[calc(25%-12px)]">
                    <x-site.public-card :title="$card['title']">
                        @if ($card['body'])
                            <p>{{ \Illuminate\Support\Str::limit(strip_tags((string) $card['body']), 160) }}</p>
                        @endif
                        @if (! empty($card['items']))
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($card['items'] as $item)
                                    <li class="flex gap-2"><span class="text-brand font-bold">›</span><span>{{ $item }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </x-site.public-card>
                </div>
            @endforeach
        </x-site.public-carousel>

        @if ($applyHref)
            <div class="mt-10">
                <x-site.public-cta-band
                    :title="__('site.products.apply_now')"
                    :body="$p['overview_short']"
                    :primary-href="$applyHref"
                    :primary-label="__('site.products.apply_now')"
                    :secondary-href="route('site.responsible-lending')"
                    :secondary-label="__('site.footer.responsible_lending')"
                />
            </div>
        @endif
    </x-site.public-section>
</x-site.layout>

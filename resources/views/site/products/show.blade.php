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
    $faqVisible = array_slice($p['faq'], 0, 3);
    $faqExtra = array_slice($p['faq'], 3);
    $cadence = app(\App\Services\GroupLendingService::class)->effectiveRepaymentCadence($product);
    $isMonthlyCadence = $cadence === 'monthly';
    $applicationFee = (float) ($p['fees']['application'] ?? 0);
    $postApprovalTotal = (float) ($p['fees']['post_approval_total'] ?? 0);
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
    <section class="relative overflow-hidden premium-gradient">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
            <a href="{{ route('site.products') }}" class="text-sm text-brand hover:underline inline-flex items-center gap-1 mb-5">
                ← {{ __('site.nav.all_products') }}
            </a>
            <div class="text-left">
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <span class="text-[11px] font-mono font-semibold text-brand/70">{{ $p['code'] }}</span>
                    <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                        {{ $isActive ? __('site.products.status_active') : __('site.products.status_coming_soon') }}
                    </span>
                </div>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-brand">{{ $p['name'] }}</h1>
                <p class="mt-2 text-lg text-brand/80 font-semibold">{{ $p['tagline'] }}</p>
                <p class="mt-3 text-gray-600 leading-relaxed max-w-2xl">{{ $p['overview_short'] }}</p>
                @if ($isActive)
                    <a href="{{ auth()->check() ? $applyUrl : $guestApplyUrl }}"
                       class="mt-6 inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-7 py-3.5 rounded-xl shadow-md transition">
                        {{ $isMarketplaceProduct ? __('site.nav.marketplace') : __('site.products.apply_now') }}
                    </a>
                @endif
            </div>

            <div class="mt-8 grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 text-left shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{{ __('site.products.amount') }}</p>
                    <p class="mt-1.5 text-sm sm:text-base font-bold text-brand tabular-nums">{{ format_money($p['limits']['min_amount'], false, 0) }} – {{ format_money($p['limits']['max_amount'], false, 0) }}</p>
                </div>
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 text-left shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{{ loan_product_rate_field_label($product) }}</p>
                    <p class="mt-1.5 text-sm sm:text-base font-bold text-brand">{{ $p['rate_label'] }}</p>
                    <p class="text-[10px] text-gray-500 mt-0.5">{{ $p['repayment_frequency_label'] }}</p>
                </div>
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 text-left shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{{ __('site.products.tenure') }}</p>
                    <p class="mt-1.5 text-sm sm:text-base font-bold tabular-nums">{{ $p['limits']['tenure_min_months'] }}–{{ $p['limits']['tenure_max_months'] }} {{ __('borrower.apply.details.months') }}</p>
                </div>
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 text-left shadow-sm">
                    <p class="text-[10px] uppercase tracking-wider text-gray-500 font-semibold">{{ __('site.product_detail.application_fee') }}</p>
                    @if ($applicationFee > 0)
                        <p class="mt-1.5 text-sm sm:text-base font-bold tabular-nums">{{ format_money($applicationFee, false, 0) }}</p>
                    @elseif ($postApprovalTotal > 0)
                        <p class="mt-1.5 text-sm sm:text-base font-bold tabular-nums">{{ format_money($postApprovalTotal, false, 0) }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">{{ __('site.product_detail.post_approval_fees') }}</p>
                    @else
                        <p class="mt-1.5 text-sm sm:text-base font-bold">{{ $p['processing_time'] }}</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">{{ __('site.product_detail.processing_time') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
        <div class="grid lg:grid-cols-2 gap-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-left">
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.product_detail.target_audience') }}</h2>
                <p class="mt-2 text-sm text-gray-700 leading-relaxed">{{ $p['target_audience'] ?: $p['overview'] }}</p>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-left">
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.product_detail.features') }}</h2>
                <ul class="mt-2 space-y-2">
                    @foreach (($p['benefits'] ?: $p['features']) as $benefit)
                        <li class="flex gap-2 text-sm text-gray-700"><span class="text-brand font-bold">›</span><span>{{ is_array($benefit) ? ($benefit['title'] ?? $benefit['label'] ?? '') : $benefit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-4">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-left">
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.product_detail.eligibility_heading') }}</h2>
                <ul class="mt-2 space-y-2">
                    @foreach (($p['eligibility'] ?? []) as $item)
                        <li class="text-sm text-gray-700">
                            <span class="font-semibold">{{ is_array($item) ? ($item['label'] ?? '') : $item }}</span>
                            @if (is_array($item) && filled($item['detail'] ?? null))
                                <span class="block text-xs text-gray-500 mt-0.5">{{ $item['detail'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-left">
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.product_detail.fees_heading') }}</h2>
                <dl class="mt-2 space-y-2 text-sm">
                    @if ($applicationFee > 0)
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ __('site.product_detail.application_fee') }}</dt><dd class="font-semibold tabular-nums">{{ format_money($applicationFee) }}</dd></div>
                    @endif
                    @foreach (($p['fees']['post_approval_lines'] ?? []) as $line)
                        <div class="flex justify-between gap-3"><dt class="text-gray-500">{{ $line['name'] ?? '' }}</dt><dd class="font-semibold tabular-nums">{{ isset($line['amount']) ? format_money($line['amount']) : '' }}</dd></div>
                    @endforeach
                    @if ($applicationFee <= 0 && empty($p['fees']['post_approval_lines']))
                        <p class="text-gray-600">{{ $p['processing_time'] }}</p>
                    @endif
                </dl>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-left">
                <h2 class="text-sm font-bold text-gray-900">{{ __('site.product_detail.documents_heading') }}</h2>
                <ul class="mt-2 space-y-2">
                    @foreach (($p['documents'] ?? []) as $doc)
                        <li class="text-sm text-gray-700">
                            <span class="font-semibold">{{ is_array($doc) ? ($doc['name'] ?? $doc['label'] ?? '') : $doc }}</span>
                            @if (is_array($doc) && filled($doc['detail'] ?? null))
                                <span class="block text-xs text-gray-500 mt-0.5">{{ $doc['detail'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 text-left">
            <h2 class="text-sm font-bold text-gray-900">{{ __('seo.how_to_apply') }}</h2>
            <ol class="mt-3 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($p['apply_steps'] ?? [] as $step)
                    <li class="text-sm text-gray-700">
                        <span class="font-semibold text-brand">{{ $step['title'] ?? '' }}</span>
                        <span class="block text-xs text-gray-600 mt-1">{{ $step['body'] ?? '' }}</span>
                    </li>
                @endforeach
            </ol>
            @if ($isActive)
                <a href="{{ auth()->check() ? $applyUrl : $guestApplyUrl }}"
                   class="mt-5 inline-flex rounded-xl bg-brand text-white font-bold px-6 py-3">{{ __('site.products.apply_now') }}</a>
            @endif
        </div>
    </section>

    {{-- Calculator --}}
    <section class="premium-gradient border-y border-gray-100 py-10 lg:py-12"
             x-data="productCalculator(@js([
                'min' => $p['limits']['min_amount'],
                'max' => $p['limits']['max_amount'],
                'tmin' => $p['limits']['tenure_min_months'],
                'tmax' => $p['limits']['tenure_max_months'],
                'tiers' => $p['tiers'] ?? [],
                'rate' => app(\App\Services\DisplayedRateService::class)->displayedMonthlyRate($product, (float) $p['limits']['min_amount']),
                'cadence' => $cadence,
             ]))">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-6 sm:p-8 shadow-sm text-left">
                <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.product_detail.calculator_eyebrow') }}</p>
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">{{ __('site.product_detail.calculator') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('site.product_detail.calculator_hint') }}</p>

                <div class="mt-6">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>{{ __('site.products.amount') }}</span>
                        <span class="font-semibold tabular-nums" x-text="formatMoney(amount)"></span>
                    </div>
                    <input type="range" :min="config.min" :max="config.max" step="50000" x-model.number="amount" class="w-full accent-brand">
                </div>

                <div class="mt-6">
                    <div class="flex justify-between text-sm text-gray-600 mb-2">
                        <span>{{ __('site.products.tenure') }}</span>
                        <span class="font-semibold"><span x-text="tenure"></span> {{ __('borrower.apply.details.months') }}</span>
                    </div>
                    <input type="range" :min="config.tmin" :max="config.tmax" step="1" x-model.number="tenure" class="w-full accent-brand">
                </div>

                <div class="mt-8 grid sm:grid-cols-2 gap-4">
                    <div class="rounded-xl bg-brand-muted/50 p-5 text-center">
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">
                            {{ $isMonthlyCadence ? __('site.product_detail.monthly_payment') : __('site.product_detail.weekly_payment') }}
                        </div>
                        <div class="text-2xl font-bold text-brand mt-2 tabular-nums" x-text="formatMoney(installment)"></div>
                    </div>
                    <div class="rounded-xl bg-brand-muted/50 p-5 text-center">
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.total_repayment') }}</div>
                        <div class="text-2xl font-bold text-gray-900 mt-2 tabular-nums" x-text="formatMoney(total)"></div>
                    </div>
                </div>

                @if ($isActive)
                    <a href="{{ auth()->check() ? $applyUrl : $guestApplyUrl }}"
                       class="mt-8 inline-flex w-full sm:w-auto items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-8 py-4 rounded-xl transition shadow-md">
                        {{ __('site.products.apply_now') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10" x-data="{ open: 0, showAll: false }">
        <h2 class="text-xl sm:text-2xl font-bold text-left mb-6">{{ __('site.product_detail.faq_heading') }}</h2>
        <div class="space-y-3">
            @foreach ($faqVisible as $i => $item)
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden text-left">
                    <button type="button" @click="open === {{ $i }} ? open = -1 : open = {{ $i }}"
                            class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-sm font-semibold text-gray-900">
                        <span>{{ $item['q'] ?? $item['question'] ?? '' }}</span>
                        <span class="text-brand" x-text="open === {{ $i }} ? '−' : '+'"></span>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-4 pb-4 text-sm text-gray-600">{{ $item['a'] ?? $item['answer'] ?? '' }}</div>
                </div>
            @endforeach
            @if (count($faqExtra) > 0)
                <div x-show="showAll" x-cloak class="space-y-3">
                    @foreach ($faqExtra as $j => $item)
                        <div class="rounded-2xl bg-white ring-1 ring-brand/10 overflow-hidden text-left">
                            <button type="button" @click="open === {{ $j + 100 }} ? open = -1 : open = {{ $j + 100 }}"
                                    class="w-full flex items-center justify-between gap-3 px-4 py-3.5 text-sm font-semibold text-gray-900">
                                <span>{{ $item['q'] ?? $item['question'] ?? '' }}</span>
                                <span class="text-brand" x-text="open === {{ $j + 100 }} ? '−' : '+'"></span>
                            </button>
                            <div x-show="open === {{ $j + 100 }}" x-cloak class="px-4 pb-4 text-sm text-gray-600">{{ $item['a'] ?? $item['answer'] ?? '' }}</div>
                        </div>
                    @endforeach
                </div>
                <button type="button" @click="showAll = !showAll" class="text-sm font-semibold text-brand" x-text="showAll ? '−' : '+'"></button>
            @endif
        </div>
    </section>
</x-site.layout>

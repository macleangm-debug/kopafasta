@php
    $p = $presentation;
    $isActive = $p['is_active'];
    $isMarketplaceProduct = is_marketplace_loan_product($product->code);
    $applyUrl = $isMarketplaceProduct
        ? (auth()->check() ? route('site.borrower.marketplace') : route('site.marketplace'))
        : route('site.borrower.apply', ['product' => $product->id]);
    $guestApplyUrl = $isMarketplaceProduct
        ? route('site.login', ['redirect' => route('site.marketplace')])
        : route('site.login', ['redirect' => route('site.borrower.apply', ['product' => $product->id])]);
    $faqVisible = array_slice($p['faq'], 0, 3);
    $faqExtra = array_slice($p['faq'], 3);
    $cadence = app(\App\Services\GroupLendingService::class)->effectiveRepaymentCadence($product);
    $isMonthlyCadence = $cadence === 'monthly';
@endphp
<x-site.layout :title="$p['name'].' — '.brand_name()">
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
            <a href="{{ route('site.products') }}" class="text-sm text-white/70 hover:text-white inline-flex items-center gap-1 mb-6 transition">
                ← {{ __('site.nav.all_products') }}
            </a>
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2 mb-4">
                        <span class="text-[11px] font-mono font-semibold text-brand-gold">{{ $p['code'] }}</span>
                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $isActive ? 'bg-emerald-500/20 text-emerald-100' : 'bg-white/10 text-white/70' }}">
                            {{ $isActive ? __('site.products.status_active') : __('site.products.status_coming_soon') }}
                        </span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight">{{ $p['name'] }}</h1>
                    <p class="mt-3 text-lg text-brand-gold font-medium">{{ $p['tagline'] }}</p>
                    <p class="mt-3 text-white/75 leading-relaxed max-w-lg">{{ $p['overview_short'] }}</p>
                    @if ($isActive)
                        <a href="{{ auth()->check() ? $applyUrl : $guestApplyUrl }}"
                           class="mt-8 inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-4 rounded-xl shadow-lg transition">
                            {{ $isMarketplaceProduct ? __('site.nav.marketplace') : __('site.products.apply_now') }}
                        </a>
                    @endif
                </div>
                <div class="hidden sm:flex justify-center lg:justify-end">
                    <x-site.product-illustration :code="$p['code']" size="hero" class="max-w-sm w-full" />
                </div>
            </div>
        </div>
    </section>

    {{-- Key metrics --}}
    <section class="bg-[#faf8f5] border-b border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="glass-card p-4 sm:p-5 text-center">
                <div class="text-[10px] sm:text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.limits') }}</div>
                <div class="text-sm sm:text-lg font-bold mt-2 text-brand tabular-nums">{{ format_money($p['limits']['min_amount'], false, 0) }} – {{ format_money($p['limits']['max_amount'], false, 0) }}</div>
            </div>
            <div class="glass-card p-4 sm:p-5 text-center">
                <div class="text-[10px] sm:text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.products.tenure') }}</div>
                <div class="text-sm sm:text-lg font-bold mt-2 tabular-nums">{{ $p['limits']['tenure_min_months'] }}–{{ $p['limits']['tenure_max_months'] }} mo</div>
            </div>
            <div class="glass-card p-4 sm:p-5 text-center">
                <div class="text-[10px] sm:text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.rates') }}</div>
                <div class="text-sm sm:text-lg font-bold mt-2 text-brand">{{ $p['rate_label'] }} / mo</div>
            </div>
            <div class="glass-card p-4 sm:p-5 text-center col-span-2 lg:col-span-1">
                <div class="text-[10px] sm:text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.processing_time') }}</div>
                <div class="text-sm sm:text-lg font-bold mt-2">{{ $p['processing_time'] }}</div>
            </div>
        </div>
    </section>

    @include('site.products._details-tabs', ['p' => $p])

    {{-- Calculator (before FAQ for better flow) --}}
    <section class="premium-gradient border-y border-gray-100 py-12 lg:py-16"
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
            <div class="glass-card p-6 sm:p-8 ring-2 ring-brand/10">
                <div class="flex items-start gap-4 mb-6">
                    <x-site.product-illustration :code="$p['code']" size="sm" class="hidden sm:block shrink-0" />
                    <div>
                        <p class="text-xs uppercase tracking-widest text-brand font-semibold">{{ __('site.product_detail.calculator_eyebrow') }}</p>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-900 mt-1">{{ __('site.product_detail.calculator') }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ __('site.product_detail.calculator_hint') }}</p>
                    </div>
                </div>

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
                        <span class="font-semibold"><span x-text="tenure"></span> mo</span>
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

                <p class="mt-4 text-xs text-gray-500 text-center">
                    {{ __('site.products.rate_at_amount') }}: <span class="font-semibold text-brand" x-text="formatRate(currentRate)"></span>
                </p>

                @if ($isActive)
                    <a href="{{ auth()->check() ? $applyUrl : $guestApplyUrl }}"
                       class="mt-8 inline-flex w-full sm:w-auto items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-8 py-4 rounded-xl transition shadow-md">
                        {{ __('site.products.apply_now') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-12" x-data="{ open: 0, showAll: false }">
        <h2 class="text-xl sm:text-2xl font-bold text-center mb-6">{{ __('site.product_detail.faq_heading') }}</h2>
        <div class="space-y-3">
            @foreach ($faqVisible as $i => $item)
                <div class="glass-card overflow-hidden">
                    <button type="button" @click="open === {{ $i }} ? open = -1 : open = {{ $i }}"
                            class="w-full px-5 py-4 flex items-center justify-between text-left font-medium text-sm">
                        <span>{{ $item['q'] }}</span>
                        <svg :class="open === {{ $i }} ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-5 pb-4 text-sm text-gray-600 border-t border-gray-100/80 pt-3">{{ $item['a'] }}</div>
                </div>
            @endforeach
            @if (count($faqExtra) > 0)
                <div x-show="showAll" x-collapse x-cloak class="space-y-3">
                    @foreach ($faqExtra as $j => $item)
                        @php $idx = $j + 3; @endphp
                        <div class="glass-card overflow-hidden">
                            <button type="button" @click="open === {{ $idx }} ? open = -1 : open = {{ $idx }}"
                                    class="w-full px-5 py-4 flex items-center justify-between text-left font-medium text-sm">
                                <span>{{ $item['q'] }}</span>
                                <svg :class="open === {{ $idx }} ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                            </button>
                            <div x-show="open === {{ $idx }}" x-cloak class="px-5 pb-4 text-sm text-gray-600 border-t border-gray-100/80 pt-3">{{ $item['a'] }}</div>
                        </div>
                    @endforeach
                </div>
                <button type="button" @click="showAll = !showAll"
                        class="w-full text-sm font-semibold text-brand hover:underline py-2"
                        x-text="showAll ? @js(__('site.product_detail.show_less')) : @js(__('site.product_detail.show_all_details'))"></button>
            @endif
        </div>
    </section>

    @include('site.products._related-products', ['products' => $otherProducts ?? collect()])

    <script>
        function productCalculator(config) {
            return {
                config,
                amount: config.min,
                tenure: config.tmin,
                resolveMonthlyRate(amount) {
                    const tiers = this.config.tiers || [];
                    if (tiers.length) {
                        const tier = tiers.find(t => amount >= t.min && amount <= t.max);
                        if (tier) return tier.rate;
                    }
                    return this.config.rate || 0;
                },
                get currentRate() {
                    return this.resolveMonthlyRate(Number(this.amount) || 0);
                },
                get monthly() {
                    const principal = Number(this.amount) || 0;
                    const months = Number(this.tenure) || 1;
                    const rate = this.currentRate;
                    return Math.round((principal / months) + (principal * rate));
                },
                get weekly() {
                    const principal = Number(this.amount) || 0;
                    const months = Number(this.tenure) || 1;
                    const rate = this.currentRate;
                    const periods = Math.max(1, Math.round(months * 4.33));
                    const periodRate = rate / 4;
                    return Math.round((principal / periods) + (principal * periodRate));
                },
                get installment() {
                    return (this.config.cadence || 'weekly') === 'monthly' ? this.monthly : this.weekly;
                },
                get total() {
                    const principal = Number(this.amount) || 0;
                    const months = Number(this.tenure) || 1;
                    const rate = this.currentRate;
                    if ((this.config.cadence || 'weekly') === 'monthly') {
                        return Math.round(this.monthly * months);
                    }
                    const periods = Math.max(1, Math.round(months * 4.33));
                    return Math.round(this.weekly * periods);
                },
                formatMoney(value) {
                    return 'TZS ' + Math.round(value).toLocaleString('en-US');
                },
                formatRate(rate) {
                    return (Number(rate) * 100).toFixed(1) + '% / mo';
                },
            };
        }
    </script>
</x-site.layout>

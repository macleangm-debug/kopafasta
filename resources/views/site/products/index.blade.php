@php
    $displayedRate = app(\App\Services\DisplayedRateService::class);
    $tierService = app(\App\Services\LoanRateTierService::class);
    $catalogProducts = $products->map(fn ($p) => [
        'id' => $p->id,
        'code' => $p->code,
        'status' => $p->status,
        'name' => $p->name,
        'description' => $p->description,
        'rate_label' => $displayedRate->formatBorrowerRateRange($p),
        'rate' => (float) $displayedRate->displayedMonthlyRate($p),
        'tiers' => $tierService->tiersForProduct($p),
        'min' => (float) $p->min_amount,
        'max' => (float) $p->max_amount,
        'tmin' => (int) $p->tenure_min_months,
        'tmax' => (int) $p->tenure_max_months,
        'requires_collateral' => $p->requires_collateral,
        'requires_guarantor' => $p->requires_guarantor,
    ]);
@endphp
<x-site.layout :title="brand_title(__('site.nav.all_products'))">
    <section class="bg-brand text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
            <h1 class="text-3xl sm:text-4xl font-bold">{{ __('site.products.all_title') }}</h1>
            <p class="mt-3 text-white/80 max-w-2xl">{{ __('site.products.all_subtitle') }}</p>
        </div>
    </section>
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16"
             x-data="loanProducts({ selectedId: {{ $products->first()?->id ?? 0 }}, products: @json($catalogProducts) })"
             x-init="init()">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">Catalogue</p>
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">All loan products</h1>
        <p class="mt-3 text-gray-600 max-w-2xl">Pick the one that matches your need. All applications go through the same fast, secure wizard.</p>

        <div class="mt-10 overflow-x-auto pb-4 -mx-4 px-4 snap-x snap-mandatory">
            <div class="flex gap-5 w-max min-w-full">
                <template x-for="product in products" :key="product.id">
                    <article @click="select(product.id)"
                             :class="selected === product.id ? 'ring-2 ring-amber-400 shadow-2xl' : 'ring-1 ring-gray-200 shadow-sm'"
                             class="snap-start shrink-0 w-[min(320px,calc(100vw-2rem))] rounded-3xl bg-white p-6 transition duration-300 cursor-pointer">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex items-center text-[11px] font-mono font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded" x-text="product.code"></span>
                            <span class="inline-flex items-center rounded-full text-[11px] font-semibold px-2.5 py-1.5 text-white"
                                  :class="product.status === 'coming_soon' ? 'bg-slate-500' : 'bg-emerald-600'"
                                  x-text="product.status.replace('_', ' ').toUpperCase()"></span>
                        </div>
                        <div class="mt-4">
                            <h3 class="text-xl font-semibold" x-text="product.name"></h3>
                            <p class="mt-3 text-sm text-gray-600" x-text="product.description"></p>
                        </div>
                        <div class="mt-5 text-xs text-gray-500 grid gap-2">
                            <div class="flex items-center justify-between">
                                <span>Amount range</span>
                                <span x-text="formatTzs(product.min) + ' – ' + formatTzs(product.max)"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Tenure</span>
                                <span x-text="product.tmin + '–' + product.tmax + ' mo'"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Monthly rate</span>
                                <span x-text="product.rate_label + ' / mo'"></span>
                            </div>
                        </div>

                        <div x-show="selected === product.id" x-collapse class="mt-5 rounded-3xl bg-amber-50 p-4 text-sm text-gray-700">
                            <div class="font-semibold mb-2">Quick detail</div>
                            <div class="space-y-2">
                                <div x-text="product.requires_collateral ? 'Requires collateral' : 'No collateral needed'"></div>
                                <div x-text="product.requires_guarantor ? 'May require a guarantor' : 'No guarantor required'"></div>
                            </div>
                            <div class="mt-4 text-[11px] uppercase tracking-wider text-amber-700">Tap this card to keep it selected</div>
                        </div>
                    </article>
                </template>
            </div>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-[1.4fr_0.9fr]">
            <div class="rounded-3xl border border-gray-200 bg-white p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-amber-600">Selected product</p>
                        <h2 class="text-2xl font-semibold mt-2" x-text="current.name"></h2>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-emerald-600 px-3 py-1 text-[11px] font-semibold text-white"
                          x-text="current.status === 'coming_soon' ? 'Coming soon' : 'Active'"></span>
                </div>
                <p class="mt-4 text-gray-600" x-text="current.description"></p>

                <div class="mt-8 grid gap-4 sm:grid-cols-3">
                    <div class="rounded-3xl bg-gray-50 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">Min amount</div>
                        <div class="mt-2 font-semibold" x-text="formatTzs(current.min)"></div>
                    </div>
                    <div class="rounded-3xl bg-gray-50 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">Max amount</div>
                        <div class="mt-2 font-semibold" x-text="formatTzs(current.max)"></div>
                    </div>
                    <div class="rounded-3xl bg-gray-50 p-4">
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">Monthly rate</div>
                        <div class="mt-2 font-semibold" x-text="current.rate_label + ' / mo'"></div>
                    </div>
                </div>

                <div class="mt-8 rounded-3xl border border-gray-200 bg-gray-50 p-6">
                    <h3 class="text-lg font-semibold">Instant repayment estimate</h3>
                    <p class="mt-2 text-sm text-gray-600">Adjust amount and tenure to preview monthly payments instantly.</p>

                    <div class="mt-6">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Loan amount</span>
                            <span class="font-semibold" x-text="formatTzs(amount)"></span>
                        </div>
                        <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="amount" class="w-full accent-amber-500 mt-3">
                        <div class="flex justify-between text-[11px] text-gray-500 mt-2">
                            <span x-text="formatTzs(current.min)"></span>
                            <span x-text="formatTzs(current.max)"></span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <div class="flex items-center justify-between text-sm text-gray-600">
                            <span>Duration</span>
                            <span class="font-semibold"><span x-text="tenure"></span> months</span>
                        </div>
                        <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="tenure" class="w-full accent-amber-500 mt-3">
                        <div class="flex justify-between text-[11px] text-gray-500 mt-2">
                            <span x-text="current.tmin + ' mo'"></span>
                            <span x-text="current.tmax + ' mo'"></span>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-4 sm:grid-cols-2">
                        <div class="rounded-3xl bg-white p-5 border border-gray-200">
                            <div class="text-[11px] uppercase tracking-wider text-gray-500">Monthly payment</div>
                            <div class="mt-3 text-2xl font-semibold text-gray-900" x-text="formatTzs(monthly)"></div>
                        </div>
                        <div class="rounded-3xl bg-white p-5 border border-gray-200">
                            <div class="text-[11px] uppercase tracking-wider text-gray-500">Total repayment</div>
                            <div class="mt-3 text-2xl font-semibold text-gray-900" x-text="formatTzs(total)"></div>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <a :href="applyUrl"
                           :class="current.status === 'coming_soon' ? 'cursor-not-allowed bg-slate-400 hover:bg-slate-400' : 'bg-amber-500 hover:bg-amber-400 text-gray-900'"
                           class="inline-flex items-center justify-center rounded-full px-6 py-3 font-semibold text-white transition"
                           x-bind:aria-disabled="current.status === 'coming_soon'"
                           x-bind:tabindex="current.status === 'coming_soon' ? -1 : 0"
                           x-text="current.status === 'coming_soon' ? 'Coming soon' : 'Apply now'"
                           :disabled="current.status === 'coming_soon'"></a>
                        <a :href="detailsUrl"
                           class="inline-flex items-center justify-center rounded-full border border-gray-200 bg-white text-gray-900 px-6 py-3 font-semibold hover:border-amber-400 hover:text-amber-700 transition">
                            View product details
                        </a>
                    </div>
                </div>
            </div>

            <aside class="rounded-3xl border border-gray-200 bg-white p-8">
                <h3 class="text-lg font-semibold">Why this product?</h3>
                <p class="mt-3 text-sm text-gray-600">The selected product updates instantly as you swipe through the catalog. This is the first step in your borrower journey — choose the right loan, check terms, then apply with confidence.</p>
                <div class="mt-6 space-y-4 text-sm text-gray-700">
                    <div class="rounded-3xl bg-amber-50 p-4">
                        <div class="font-semibold">Flexible terms</div>
                        <div class="mt-1">Configure loan amount and tenure with live repayment estimates.</div>
                    </div>
                    <div class="rounded-3xl bg-gray-50 p-4">
                        <div class="font-semibold">Transparent pricing</div>
                        <div class="mt-1">Every product shows the effective monthly rate and repayment range.</div>
                    </div>
                    <div class="rounded-3xl bg-emerald-50 p-4">
                        <div class="font-semibold">Coming soon products</div>
                        <div class="mt-1">Browse future offerings without applying until they are live.</div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    <script>
        function loanProducts(data) {
            return {
                products: data.products || [],
                selected: data.selectedId || (data.products[0]?.id ?? null),
                amount: 0,
                tenure: 0,
                init() {
                    if (! this.selected && this.products.length) {
                        this.selected = this.products[0].id;
                    }
                    this.onProduct();
                },
                select(id) {
                    this.selected = id;
                    this.onProduct();
                },
                onProduct() {
                    const current = this.products.find(p => p.id === this.selected) || this.products[0] || {};
                    if (! current) {
                        return;
                    }
                    this.amount = Math.min(Math.max(this.amount || current.min, current.min), current.max);
                    this.tenure = Math.min(Math.max(this.tenure || current.tmin, current.tmin), current.tmax);
                },
                get current() {
                    return this.products.find(p => p.id === this.selected) || this.products[0] || {};
                },
                resolveMonthlyRate(product, amount) {
                    if (! product) return 0;
                    const tiers = product.tiers || [];
                    if (tiers.length) {
                        const tier = tiers.find(t => amount >= t.min && amount <= t.max);
                        if (tier) return tier.rate;
                    }
                    return product.rate || 0;
                },
                get monthly() {
                    const r = this.resolveMonthlyRate(this.current, this.amount);
                    const n = this.tenure || 1;
                    return Math.round((this.amount / n) + (this.amount * r));
                },
                get total() {
                    return Math.round(this.monthly * (this.tenure || 1));
                },
                get applyUrl() {
                    return this.current.status === 'coming_soon' ? '#' : '{{ route('site.apply.show') }}?product=' + this.current.id;
                },
                get detailsUrl() {
                    return '{{ url('/loans/product') }}/' + this.current.code;
                },
                formatTzs(value, decimals = 0) {
                    return window.formatMoney ? window.formatMoney(value, { currency: 'TZS', decimals }) : ('TZS ' + value);
                },
            }
        }
    </script>
</x-site.layout>

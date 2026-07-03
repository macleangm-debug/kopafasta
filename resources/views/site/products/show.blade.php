@php
    $p = $presentation;
    $isActive = $p['is_active'];
@endphp
<x-site.layout :title="$p['name'].' — '.brand_name()">
    {{-- Banner --}}
    <section class="relative overflow-hidden bg-brand text-white">
        <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
        <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-14 lg:py-20">
            <a href="{{ route('site.products') }}" class="text-sm text-white/70 hover:text-white inline-flex items-center gap-1 mb-6 transition">
                ← {{ __('site.nav.all_products') }}
            </a>
            <div class="flex flex-wrap items-start justify-between gap-8">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="size-14 rounded-2xl bg-white/10 grid place-items-center text-3xl">{{ $p['icon'] }}</span>
                        <div>
                            <span class="text-[11px] font-mono font-semibold text-brand-gold">{{ $p['code'] }}</span>
                            <span class="ml-2 text-[11px] font-semibold px-2.5 py-1 rounded-full {{ $isActive ? 'bg-emerald-500/20 text-emerald-100' : 'bg-white/10 text-white/70' }}">
                                {{ $isActive ? __('site.products.status_active') : __('site.products.status_coming_soon') }}
                            </span>
                        </div>
                    </div>
                    <h1 class="text-3xl sm:text-5xl font-bold tracking-tight">{{ $p['name'] }}</h1>
                    <p class="mt-2 text-sm uppercase tracking-widest text-brand-gold">{{ $p['category_label'] }}</p>
                    <p class="mt-4 text-lg text-white/80 leading-relaxed">{{ $p['description'] }}</p>
                </div>
                @if ($isActive)
                    <a href="{{ auth()->check() ? route('site.borrower.apply', ['product' => $product->id]) : route('site.login', ['redirect' => route('site.borrower.apply', ['product' => $product->id])]) }}" class="inline-flex items-center gap-2 bg-brand-gold hover:bg-yellow-400 text-brand font-bold px-8 py-4 rounded-xl shadow-lg transition">
                        {{ __('site.products.apply_now') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    {{-- Overview & audience --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16 grid lg:grid-cols-2 gap-6">
        <div class="glass-card p-8">
            <h2 class="text-xs uppercase tracking-widest text-brand font-semibold mb-3">{{ __('site.product_detail.overview') }}</h2>
            <p class="text-gray-700 leading-relaxed">{{ $p['overview'] }}</p>
        </div>
        <div class="glass-card p-8">
            <h2 class="text-xs uppercase tracking-widest text-brand font-semibold mb-3">{{ __('site.product_detail.target_audience') }}</h2>
            <p class="text-gray-700 leading-relaxed">{{ $p['target_audience'] }}</p>
        </div>
    </section>

    {{-- Key metrics --}}
    <section class="bg-[#faf8f5] border-y border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass-card p-5 text-center">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.limits') }}</div>
                <div class="text-lg font-bold mt-2 text-brand tabular-nums">{{ format_money($p['limits']['min_amount'], false, 0) }} – {{ format_money($p['limits']['max_amount'], false, 0) }}</div>
            </div>
            <div class="glass-card p-5 text-center">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.products.tenure') }}</div>
                <div class="text-lg font-bold mt-2 tabular-nums">{{ $p['limits']['tenure_min_months'] }}–{{ $p['limits']['tenure_max_months'] }} mo</div>
            </div>
            <div class="glass-card p-5 text-center">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.rates') }}</div>
                <div class="text-lg font-bold mt-2 text-brand">{{ $p['rate_label'] }} / mo</div>
            </div>
            <div class="glass-card p-5 text-center">
                <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.processing_time') }}</div>
                <div class="text-lg font-bold mt-2">{{ $p['processing_time'] }}</div>
            </div>
        </div>
    </section>

    {{-- Features, eligibility, fees --}}
    <section class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16 grid lg:grid-cols-3 gap-6">
        <div class="glass-card p-8 lg:col-span-1">
            <h2 class="font-bold text-lg mb-4">{{ __('site.product_detail.features') }}</h2>
            <ul class="space-y-3">
                @foreach ($p['benefits'] as $benefit)
                    <li class="flex gap-2 text-sm text-gray-700">
                        <span class="text-brand shrink-0">✓</span>
                        <span>{{ $benefit }}</span>
                    </li>
                @endforeach
                @foreach ($p['features'] as $feature)
                    <li class="flex gap-2 text-sm text-gray-600">
                        <span class="text-brand-gold shrink-0">•</span>
                        <span>{{ $feature }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="glass-card p-8">
            <h2 class="font-bold text-lg mb-4">{{ __('site.product_detail.eligibility_heading') }}</h2>
            <ul class="space-y-4">
                @foreach ($p['eligibility'] as $item)
                    <li>
                        <p class="font-semibold text-sm text-gray-900">{{ $item['label'] }}</p>
                        <p class="text-xs text-gray-600 mt-0.5">{{ $item['detail'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="glass-card p-8">
            <h2 class="font-bold text-lg mb-4">{{ __('site.product_detail.fees_heading') }}</h2>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('site.product_detail.application_fee') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ format_money($p['fees']['application']) }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ __('site.product_detail.post_approval_fees') }}</dt>
                    <dd class="font-semibold tabular-nums">{{ format_money($p['fees']['post_approval_total']) }}</dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-gray-500">{{ $p['fees']['post_approval_detail'] }}</p>

            @if (! empty($p['rate_disclosure']))
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h3 class="text-xs uppercase tracking-widest text-gray-500 mb-2">{{ __('site.product_detail.rates') }}</h3>
                    <ul class="space-y-1 text-xs text-gray-600">
                        @foreach ($p['rate_disclosure'] as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    {{-- Documents & product specific --}}
    <section class="bg-[#faf8f5] border-y border-gray-100">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid lg:grid-cols-2 gap-6">
            <div class="glass-card p-8">
                <h2 class="font-bold text-lg mb-4">{{ __('site.product_detail.documents_heading') }}</h2>
                <ul class="space-y-3">
                    @foreach ($p['documents'] as $doc)
                        <li class="flex gap-3 text-sm">
                            <span class="size-6 rounded-full bg-brand-muted text-brand grid place-items-center text-xs shrink-0">📄</span>
                            <div>
                                <p class="font-medium text-gray-900">{{ $doc['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $doc['detail'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (! empty($p['product_specific']))
                <div class="glass-card p-8 ring-2 ring-brand-gold/20">
                    <h2 class="font-bold text-lg mb-4">{{ $p['category_label'] }}</h2>
                    <ul class="space-y-3">
                        @foreach ($p['product_specific'] as $item)
                            <li>
                                <p class="font-semibold text-sm">{{ $item['label'] }}</p>
                                <p class="text-xs text-gray-600">{{ $item['detail'] }}</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    {{-- FAQ --}}
    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12" x-data="{ open: 0 }">
        <h2 class="text-2xl font-bold text-center mb-8">{{ __('site.product_detail.faq_heading') }}</h2>
        <div class="space-y-3">
            @foreach ($p['faq'] as $i => $item)
                <div class="glass-card overflow-hidden">
                    <button type="button" @click="open === {{ $i }} ? open = -1 : open = {{ $i }}"
                            class="w-full px-5 py-4 flex items-center justify-between text-left font-medium text-sm">
                        <span>{{ $item['q'] }}</span>
                        <svg :class="open === {{ $i }} ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="open === {{ $i }}" x-cloak class="px-5 pb-4 text-sm text-gray-600 border-t border-gray-100/80 pt-3">{{ $item['a'] }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Calculator --}}
    <section class="premium-gradient border-t border-gray-100 py-12 lg:py-16"
             x-data="productCalculator(@js([
                'min' => $p['limits']['min_amount'],
                'max' => $p['limits']['max_amount'],
                'tmin' => $p['limits']['tenure_min_months'],
                'tmax' => $p['limits']['tenure_max_months'],
                'rate' => app(\App\Services\DisplayedRateService::class)->displayedMonthlyRate($product),
             ]))">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass-card p-8">
                <h2 class="text-2xl font-bold text-gray-900">{{ __('site.product_detail.calculator') }}</h2>
                <p class="mt-2 text-sm text-gray-600">{{ __('site.product_detail.calculator_hint') }}</p>

                <div class="mt-8">
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
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.monthly_payment') }}</div>
                        <div class="text-2xl font-bold text-brand mt-2 tabular-nums" x-text="formatMoney(monthly)"></div>
                    </div>
                    <div class="rounded-xl bg-brand-muted/50 p-5 text-center">
                        <div class="text-[11px] uppercase tracking-wider text-gray-500">{{ __('site.product_detail.total_repayment') }}</div>
                        <div class="text-2xl font-bold text-gray-900 mt-2 tabular-nums" x-text="formatMoney(total)"></div>
                    </div>
                </div>

                @if ($isActive)
                    <a href="{{ auth()->check() ? route('site.borrower.apply', ['product' => $product->id]) : route('site.login', ['redirect' => route('site.borrower.apply', ['product' => $product->id])]) }}"
                       class="mt-8 inline-flex w-full sm:w-auto items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-8 py-4 rounded-xl transition shadow-md">
                        {{ __('site.products.apply_now') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    <script>
        function productCalculator(config) {
            return {
                config,
                amount: config.min,
                tenure: config.tmin,
                get monthly() {
                    const principal = Number(this.amount) || 0;
                    const months = Number(this.tenure) || 1;
                    const rate = Number(this.config.rate) || 0;
                    return Math.round(principal * (1 + rate * months) / months);
                },
                get total() {
                    return this.monthly * (Number(this.tenure) || 1);
                },
                formatMoney(value) {
                    return 'TZS ' + Math.round(value).toLocaleString('en-US');
                },
            };
        }
    </script>
</x-site.layout>

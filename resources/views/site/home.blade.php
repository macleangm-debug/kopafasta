<x-site.layout title="Kopafasta — Capital that moves at your pace">

    {{-- ===== HERO ===== --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-gray-900 via-gray-900 to-amber-900">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 20% 20%, #f59e0b 0%, transparent 40%), radial-gradient(circle at 80% 70%, #fbbf24 0%, transparent 40%);"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 grid lg:grid-cols-2 gap-12 items-center">
            <div class="text-white">
                <p class="text-xs uppercase tracking-widest text-amber-300 mb-4">Trusted microfinance · Tanzania</p>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">
                    Capital that moves <br><span class="text-amber-400">at your pace.</span>
                </h1>
                <p class="mt-6 text-lg text-gray-300 max-w-xl">
                    From your first individual loan to asset-backed financing — all-in monthly rates from 15%, fully disclosed before you sign, disbursed in hours.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('site.register.borrower') }}" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full shadow-lg transition">
                        Apply for a loan
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </a>
                    <a href="{{ route('site.products') }}" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur text-white font-semibold px-6 py-3 rounded-full border border-white/20 transition">
                        Browse products
                    </a>
                </div>
                <div class="mt-8 flex flex-wrap gap-6 text-xs text-gray-400">
                    <span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-emerald-400"></span> Secure & encrypted</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-amber-400"></span> Disbursed in hours</span>
                    <span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full bg-sky-400"></span> 12,400+ members</span>
                </div>
            </div>

            {{-- Loan calculator --}}
            <div class="bg-white rounded-2xl shadow-2xl p-6 lg:p-8"
                 x-data="loanCalc({{ $products->first()?->id ?? 0 }}, {{ json_encode($products->map(fn($p)=>['id'=>$p->id,'code'=>$p->code,'name'=>$p->name,'rate'=>(float)$p->interest_rate,'min'=>(float)$p->min_amount,'max'=>(float)$p->max_amount,'tmin'=>(int)$p->tenure_min_months,'tmax'=>(int)$p->tenure_max_months])) }})">
                <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">Live quote · TZS</p>
                <h3 class="text-xl font-semibold mb-4">Loan calculator</h3>

                <label class="block text-xs font-medium text-gray-600 mb-1">Product</label>
                <select x-model="productId" @change="onProduct()" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm mb-4">
                    <template x-for="p in products" :key="p.id">
                        <option :value="p.id" x-text="p.name + ' (' + (p.rate*100).toFixed(1) + '%)'"></option>
                    </template>
                </select>

                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-600">Loan amount</span>
                    <span class="font-semibold" x-text="formatTzs(amount)"></span>
                </div>
                <input type="range" :min="current.min" :max="current.max" step="50000" x-model.number="amount" class="w-full accent-amber-500 mb-1">
                <div class="flex justify-between text-[11px] text-gray-500 mb-4">
                    <span x-text="formatTzs(current.min)"></span><span x-text="formatTzs(current.max)"></span>
                </div>

                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-gray-600">Duration</span>
                    <span class="font-semibold"><span x-text="tenure"></span> months</span>
                </div>
                <input type="range" :min="current.tmin" :max="current.tmax" step="1" x-model.number="tenure" class="w-full accent-amber-500 mb-1">
                <div class="flex justify-between text-[11px] text-gray-500 mb-6">
                    <span x-text="current.tmin + ' mo'"></span><span x-text="current.tmax + ' mo'"></span>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-amber-50 rounded-lg p-3">
                        <div class="text-[11px] uppercase tracking-wider text-amber-700">Monthly payment</div>
                        <div class="text-lg font-bold text-gray-900" x-text="formatTzs(monthly)"></div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3">
                        <div class="text-[11px] uppercase tracking-wider text-gray-600">Total repayment</div>
                        <div class="text-lg font-bold text-gray-900" x-text="formatTzs(total)"></div>
                    </div>
                </div>

                <a :href="'{{ route('site.apply.show') }}?product=' + current.id"
                   class="block text-center w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-3 rounded-full transition">
                    Apply with these terms
                </a>
                <p class="text-[11px] text-gray-500 mt-3 text-center">Estimates only. Final terms confirmed during application.</p>
            </div>
        </div>
    </section>

    {{-- ===== TRUST STRIP ===== --}}
    <section class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div><div class="text-2xl font-bold text-gray-900">TZS 14.2B</div><div class="text-xs text-gray-500 uppercase tracking-widest mt-1">Disbursed</div></div>
            <div><div class="text-2xl font-bold text-gray-900">12,400+</div><div class="text-xs text-gray-500 uppercase tracking-widest mt-1">Active members</div></div>
            <div><div class="text-2xl font-bold text-emerald-700">17.4%</div><div class="text-xs text-gray-500 uppercase tracking-widest mt-1">Net investor yield</div></div>
            <div><div class="text-2xl font-bold text-gray-900">96.3%</div><div class="text-xs text-gray-500 uppercase tracking-widest mt-1">On-time repayment</div></div>
        </div>
    </section>

    {{-- ===== PRODUCTS ===== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">Products</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Ten products. <span class="text-gray-500">One account.</span></h2>
        <p class="mt-3 text-gray-600 max-w-2xl">From TZS 50,000 starter loans to asset-backed capital. Every product shares the same secure account, the same honest pricing, the same mobile experience.</p>

        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($products as $product)
                <a href="{{ route('site.product', $product->code) }}"
                   class="group block rounded-2xl border border-gray-200 hover:border-amber-400 hover:shadow-lg transition p-6 bg-white">
                    <div class="flex items-start justify-between mb-3">
                        <span class="inline-flex items-center text-[11px] font-mono font-semibold text-amber-700 bg-amber-50 px-2 py-1 rounded">{{ $product->code }}</span>
                        <span class="text-xs text-gray-500">from <span class="font-bold text-gray-900">{{ number_format($product->interest_rate * 100, 1) }}%</span> / mo</span>
                    </div>
                    <h3 class="text-lg font-semibold group-hover:text-amber-700">{{ $product->name }}</h3>
                    <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ $product->description }}</p>
                    <div class="mt-4 text-xs text-gray-500 flex items-center justify-between">
                        <span>{{ number_format($product->min_amount / 1000) }}k – {{ number_format($product->max_amount / 1000000, 0) }}M TZS</span>
                        <span>{{ $product->tenure_min_months }}–{{ $product->tenure_max_months }} mo</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ===== INVEST WITH US ===== --}}
    <section class="bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="grid lg:grid-cols-2 gap-12 items-start">
                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-300 mb-2">For investors</p>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Don't just borrow. <span class="text-emerald-400">Earn from credit too.</span></h2>
                    <p class="mt-4 text-white/75 max-w-xl">Open an investor account and put your savings to work funding real loans to vetted Tanzanians. Two programmes — pick the one that fits your scale.</p>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <a href="{{ route('site.invest') }}" class="group rounded-2xl bg-white/5 backdrop-blur border border-white/10 hover:border-emerald-400 hover:bg-white/10 transition p-6">
                        <div class="size-11 grid place-items-center rounded-xl bg-emerald-500/20 text-2xl mb-3">📈</div>
                        <h3 class="text-lg font-bold">Individual investor</h3>
                        <p class="mt-1.5 text-sm text-white/70">Start from TZS 50,000. Earn 12–24% per year.</p>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-emerald-300 group-hover:gap-2 transition-all">Learn more →</span>
                    </a>
                    <a href="{{ route('site.capital-partners') }}" class="group rounded-2xl bg-white/5 backdrop-blur border border-white/10 hover:border-indigo-400 hover:bg-white/10 transition p-6">
                        <div class="size-11 grid place-items-center rounded-xl bg-indigo-500/20 text-2xl mb-3">🏛️</div>
                        <h3 class="text-lg font-bold">Capital partner</h3>
                        <p class="mt-1.5 text-sm text-white/70">Banks, MFIs, DFIs, family offices. $50K+ commitments.</p>
                        <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-indigo-300 group-hover:gap-2 transition-all">Explore programme →</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== PARTNERS ===== --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">For service partners</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Grow your business with Kopafasta.</h2>
        <p class="mt-3 text-gray-600 max-w-2xl">GPS installers, valuers, insurers and yard partners — receive a steady stream of jobs nationwide with fast settlement and a mobile-first vendor portal.</p>

        <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach ([
                ['📡','GPS installers','Schedule installs from your phone. Same-day payout on completion.'],
                ['🛡️','Insurance providers','Quote and bind comprehensive cover for collateralised loans.'],
                ['📋','Valuers','Inspect and value assets via our app. Photo-evidence required.'],
                ['🏭','Yard &amp; collections','Help us recover and remarket repossessed assets.'],
            ] as [$icon, $title, $body])
                <div class="rounded-2xl border border-gray-200 hover:border-amber-400 hover:shadow-lg p-6 transition bg-white">
                    <div class="size-11 grid place-items-center rounded-xl bg-amber-100 text-2xl mb-3">{{ $icon }}</div>
                    <h3 class="text-base font-bold text-gray-900">{!! $title !!}</h3>
                    <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">{!! $body !!}</p>
                </div>
            @endforeach
        </div>
        <div class="mt-8">
            <a href="{{ route('site.register.vendor') }}" class="inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-semibold px-6 py-3 rounded-full">
                Become a vendor
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
        </div>
    </section>

    {{-- ===== CTA ===== --}}
    <section class="bg-gray-50 border-t border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Your first loan <span class="text-amber-600">is five minutes away.</span></h2>
            <p class="mt-3 text-gray-600">Phone and password — that's all we need to begin.</p>
            <div class="mt-6 flex flex-wrap gap-3 justify-center">
                <a href="{{ route('site.register.borrower') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-3 rounded-full">Register now</a>
                <a href="{{ route('site.login') }}" class="bg-white border border-gray-300 hover:border-gray-900 text-gray-900 font-semibold px-6 py-3 rounded-full">I already have an account</a>
            </div>
        </div>
    </section>

    <script>
        function loanCalc(initialId, products) {
            return {
                products,
                productId: initialId,
                current: products[0] || {},
                amount: products[0]?.min || 0,
                tenure: products[0]?.tmin || 1,
                init() { this.onProduct(); },
                onProduct() {
                    this.current = this.products.find(p => p.id == this.productId) || this.products[0];
                    this.amount = Math.min(Math.max(this.amount, this.current.min), this.current.max) || this.current.min;
                    this.tenure = Math.min(Math.max(this.tenure, this.current.tmin), this.current.tmax) || this.current.tmin;
                },
                get monthly() {
                    const r = this.current.rate || 0;
                    const n = this.tenure || 1;
                    // simple interest monthly: principal/n + principal*r
                    return Math.round((this.amount / n) + (this.amount * r));
                },
                get total() { return this.monthly * this.tenure; },
                formatTzs(v) {
                    return 'TZS ' + new Intl.NumberFormat('en-US').format(Math.round(v || 0));
                },
            };
        }
    </script>
</x-site.layout>

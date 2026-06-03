<x-site.layout title="Invest with Kopafasta — earn from Tanzania's credit market"
                description="Open an investor account from TZS 50,000 and earn 12–24% per year backed by vetted microloans.">

    {{-- HERO --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-900">
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 20% 20%, #10b981 0%, transparent 40%), radial-gradient(circle at 80% 70%, #34d399 0%, transparent 40%);"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 grid lg:grid-cols-2 gap-12 items-center">
            <div class="text-white">
                <p class="text-xs uppercase tracking-widest text-emerald-300 mb-4">Investor programme · Tanzania</p>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">
                    Earn from <span class="text-emerald-400">real credit,</span><br>not speculation.
                </h1>
                <p class="mt-6 text-lg text-white/80 max-w-xl">
                    Deploy capital into curated, risk-graded microloan pools and earn predictable monthly returns of <strong class="text-white">12–24% per year</strong>. Full transparency, monthly statements, withdraw any time.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('site.register.investor') }}" class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-semibold px-6 py-3 rounded-full shadow-lg transition">
                        Open investor account
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </a>
                    <a href="#pools" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur text-white font-semibold px-6 py-3 rounded-full border border-white/20 transition">
                        Browse pools
                    </a>
                </div>
                <div class="mt-8 grid grid-cols-3 gap-6 max-w-md">
                    <div><div class="text-2xl font-bold text-white">12–24%</div><div class="text-xs text-white/60">Net yield p.a.</div></div>
                    <div><div class="text-2xl font-bold text-white">96.3%</div><div class="text-xs text-white/60">On-time repay</div></div>
                    <div><div class="text-2xl font-bold text-white">TZS 50K</div><div class="text-xs text-white/60">Minimum invest</div></div>
                </div>
            </div>

            {{-- Returns calculator --}}
            <div class="bg-white rounded-2xl shadow-2xl p-6 lg:p-8" x-data="returnsCalc()">
                <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">Returns estimator</p>
                <h3 class="text-xl font-semibold mb-5 text-slate-900">How much could you earn?</h3>

                <label class="block text-xs font-medium text-slate-600 mb-1.5">Investment amount</label>
                <div class="flex items-center justify-between text-sm mb-1">
                    <span class="text-slate-600">TZS</span>
                    <span class="font-semibold text-slate-900" x-text="formatTzs(amount)"></span>
                </div>
                <input type="range" min="50000" max="50000000" step="50000" x-model.number="amount" class="w-full accent-emerald-600 mb-4" />

                <label class="block text-xs font-medium text-slate-600 mb-2 mt-3">Risk &amp; pool mix</label>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <template x-for="opt in mixes" :key="opt.id">
                        <button type="button" @click="mix = opt.id"
                                class="px-3 py-2 rounded-xl text-xs font-semibold border-2 transition"
                                :class="mix === opt.id ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600 hover:border-slate-400'"
                                x-text="opt.label"></button>
                    </template>
                </div>

                <label class="block text-xs font-medium text-slate-600 mb-1.5">Tenor: <span x-text="months"></span> months</label>
                <input type="range" min="3" max="36" step="3" x-model.number="months" class="w-full accent-emerald-600 mb-5" />

                <div class="grid grid-cols-2 gap-3 mb-5">
                    <div class="bg-emerald-50 rounded-xl p-4">
                        <div class="text-[11px] uppercase tracking-wider text-emerald-700">Monthly earnings</div>
                        <div class="text-xl font-bold text-slate-900" x-text="formatTzs(monthly)"></div>
                    </div>
                    <div class="bg-slate-50 rounded-xl p-4">
                        <div class="text-[11px] uppercase tracking-wider text-slate-600">Total returns</div>
                        <div class="text-xl font-bold text-slate-900" x-text="formatTzs(total)"></div>
                    </div>
                </div>
                <a href="{{ route('site.register.investor') }}" class="block text-center w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 rounded-full">Start investing</a>
                <p class="text-[11px] text-slate-500 mt-3 text-center">Illustrative. Returns vary by pool performance and default rates.</p>
            </div>
        </div>
    </section>

    {{-- WHY KOPAFASTA --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">Why Kopafasta</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">A new kind of investment account.</h2>
        <p class="mt-3 text-slate-600 max-w-2xl">Backed by real loans to vetted Tanzanians. Repaid every month. No middlemen, no hidden fees, no lock-ups beyond the loan tenor.</p>

        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach ([
                ['🛡️','Asset-backed underwriting','Every loan is screened through 9 risk signals: CRB history, M-Pesa flows, employment proof, and (for asset loans) collateral with GPS tracking.'],
                ['🪟','Loan-level transparency','See exactly which borrowers your money is funding, repayment history, and default events — in real time.'],
                ['🏦','Multi-channel funding','Top up via bank transfer, M-Pesa, Airtel Money, Tigo Pesa or USDC stablecoin. Withdraw to any of the above.'],
                ['📜','Monthly statements','Audited statements + Tanzanian tax-ready summaries delivered on the 5th of every month.'],
                ['⚙️','Auto-invest engine','Set your risk preference once. New deposits are automatically allocated across matching pools.'],
                ['🤝','Dedicated support','Tanzanian phone &amp; WhatsApp support during business hours. Account managers for portfolios above TZS 10M.'],
            ] as [$icon, $title, $body])
                <div class="rounded-2xl border border-slate-200 hover:border-emerald-400 hover:shadow-lg p-6 transition bg-white">
                    <div class="size-11 grid place-items-center rounded-xl bg-emerald-100 text-2xl mb-4">{{ $icon }}</div>
                    <h3 class="text-lg font-bold text-slate-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- POOL MARKETPLACE --}}
    <section id="pools" class="bg-slate-50 border-t border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="flex items-end justify-between gap-6 flex-wrap mb-10">
                <div>
                    <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">Pool marketplace</p>
                    <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Four ways to deploy capital.</h2>
                    <p class="mt-3 text-slate-600 max-w-2xl">Each pool is a basket of similar loans, risk-graded and continuously rebalanced. Mix and match to build a portfolio that matches your appetite.</p>
                </div>
                <a href="{{ route('site.register.investor') }}" class="hidden sm:inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:underline">View full marketplace →</a>
            </div>

            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                @foreach ([
                    ['Salary loans','💼','low','12%','Short-term advances to vetted salaried employees of registered companies.','1.8%'],
                    ['Business SME','🏪','medium','18%','Working capital and inventory loans to growing Tanzanian SMEs.','4.5%'],
                    ['Car &amp; asset','🚗','medium','16%','Vehicle-backed loans with GPS tracking and registration retention.','3.2%'],
                    ['Emergency','⚡','high','24%','Short-tenor advances. Highest yield, elevated risk.','7.5%'],
                ] as [$name, $emoji, $risk, $yield, $desc, $def])
                    @php $riskColor = ['low' => 'emerald', 'medium' => 'amber', 'high' => 'red'][$risk]; @endphp
                    <div class="rounded-2xl bg-white border border-slate-200 hover:border-emerald-400 hover:-translate-y-1 hover:shadow-xl transition p-6">
                        <div class="flex items-center justify-between mb-4">
                            <span class="size-11 grid place-items-center rounded-xl bg-slate-100 text-2xl">{{ $emoji }}</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest px-2 py-1 rounded-full bg-{{ $riskColor }}-100 text-{{ $riskColor }}-700">{{ $risk }} risk</span>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">{!! $name !!}</h3>
                        <p class="mt-1.5 text-sm text-slate-600 leading-relaxed">{!! $desc !!}</p>
                        <div class="mt-5 pt-4 border-t border-slate-100 flex items-end justify-between">
                            <div>
                                <div class="text-[11px] uppercase tracking-wider text-slate-500">Target yield</div>
                                <div class="text-2xl font-bold text-emerald-700">{{ $yield }}<span class="text-sm text-slate-500 font-normal">/yr</span></div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] uppercase tracking-wider text-slate-500">Default</div>
                                <div class="text-sm font-bold text-slate-900">{{ $def }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- HOW IT WORKS --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">How it works</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">From sign-up to first return in four steps.</h2>

        <ol class="mt-12 grid md:grid-cols-4 gap-6">
            @foreach ([
                ['1','Open your account','Register in under a minute with email, phone &amp; ID. Verified instantly via NIDA.'],
                ['2','Top up your wallet','Deposit via bank, M-Pesa, Airtel, Tigo or stablecoin. Funds available immediately.'],
                ['3','Pick your pools','Browse the marketplace or let auto-invest allocate based on your risk preference.'],
                ['4','Earn monthly','Returns paid on the 1st of each month directly to your wallet. Withdraw or compound.'],
            ] as [$n, $t, $d])
                <li class="relative rounded-2xl border border-slate-200 p-6 bg-white">
                    <span class="absolute -top-3 left-6 size-8 grid place-items-center rounded-full bg-emerald-600 text-white font-bold text-sm">{{ $n }}</span>
                    <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $t }}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{!! $d !!}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- PERFORMANCE --}}
    <section id="performance" class="bg-gradient-to-br from-slate-900 to-emerald-950 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <p class="text-xs uppercase tracking-widest text-emerald-300 mb-2">Track record</p>
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Twelve months. Audited.</h2>
                <p class="mt-3 text-white/70 max-w-xl">Independent KPMG-audited portfolio performance. Updated every quarter — no marketing math.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-6">
                    <div class="text-3xl font-bold">TZS 14.2B</div>
                    <div class="text-sm text-white/60 mt-1">Total disbursed (LTM)</div>
                </div>
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-6">
                    <div class="text-3xl font-bold text-emerald-300">96.3%</div>
                    <div class="text-sm text-white/60 mt-1">On-time repayment</div>
                </div>
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-6">
                    <div class="text-3xl font-bold">17.4%</div>
                    <div class="text-sm text-white/60 mt-1">Weighted net yield</div>
                </div>
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-6">
                    <div class="text-3xl font-bold">3.1%</div>
                    <div class="text-sm text-white/60 mt-1">Portfolio default rate</div>
                </div>
            </div>
        </div>
    </section>

    {{-- COMPARE INDIVIDUAL VS CAPITAL --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">Choose your path</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Individual or institutional?</h2>
        <p class="mt-3 text-slate-600 max-w-2xl">Two programmes, one underlying credit book. Pick the one that fits your scale and reporting needs.</p>

        <div class="mt-10 grid md:grid-cols-2 gap-6">
            <div class="rounded-3xl border-2 border-emerald-200 bg-emerald-50/30 p-8">
                <div class="flex items-center gap-3 mb-3">
                    <span class="size-11 grid place-items-center rounded-xl bg-emerald-600 text-white text-xl">📈</span>
                    <h3 class="text-2xl font-bold text-slate-900">Individual investor</h3>
                </div>
                <p class="text-sm text-slate-700">Best for retail savers, professionals and small businesses looking for predictable monthly income.</p>
                <ul class="mt-5 space-y-2 text-sm text-slate-700">
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Start from TZS 50,000</li>
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Self-service web &amp; mobile app</li>
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Standard monthly statement</li>
                    <li class="flex gap-2"><span class="text-emerald-600">✓</span> Withdraw any time (subject to tenor)</li>
                </ul>
                <a href="{{ route('site.register.investor') }}" class="mt-6 inline-flex items-center justify-center w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-full">Open investor account</a>
            </div>
            <div class="rounded-3xl border-2 border-indigo-200 bg-indigo-50/30 p-8">
                <div class="flex items-center gap-3 mb-3">
                    <span class="size-11 grid place-items-center rounded-xl bg-indigo-700 text-white text-xl">🏛️</span>
                    <h3 class="text-2xl font-bold text-slate-900">Capital partner</h3>
                </div>
                <p class="text-sm text-slate-700">For banks, MFIs, DFIs, family offices and asset managers deploying USD 50,000 or more.</p>
                <ul class="mt-5 space-y-2 text-sm text-slate-700">
                    <li class="flex gap-2"><span class="text-indigo-700">✓</span> Custom risk-graded pools &amp; SPVs</li>
                    <li class="flex gap-2"><span class="text-indigo-700">✓</span> Dedicated relationship manager</li>
                    <li class="flex gap-2"><span class="text-indigo-700">✓</span> Loan-level reporting &amp; API access</li>
                    <li class="flex gap-2"><span class="text-indigo-700">✓</span> Audited monthly NAV statements</li>
                </ul>
                <a href="{{ route('site.capital-partners') }}" class="mt-6 inline-flex items-center justify-center w-full bg-indigo-700 hover:bg-indigo-800 text-white font-semibold py-3 rounded-full">Explore capital programme</a>
            </div>
        </div>
    </section>

    {{-- TESTIMONIALS --}}
    <section class="bg-slate-50 border-t border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">What investors say</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900 max-w-2xl">Stories from our investor community.</h2>

            <div class="mt-10 grid md:grid-cols-3 gap-6">
                @foreach ([
                    ['I used to keep my savings in a fixed-deposit earning 7%. With Kopafasta I average 18% — and I can see exactly where my money is working.', 'Asha M.', 'Pharmacist, Dar es Salaam'],
                    ['The auto-invest feature is a game changer. I set my preferences once and my returns just compound every month.', 'Joseph K.', 'Engineer, Mwanza'],
                    ['The monthly statements and tax summaries make my accountant happy. Best investor experience I have used in Tanzania.', 'Grace T.', 'Business owner, Arusha'],
                ] as [$quote, $name, $role])
                    <figure class="rounded-2xl bg-white border border-slate-200 p-6">
                        <svg class="w-6 h-6 text-emerald-500 mb-3" viewBox="0 0 24 24" fill="currentColor"><path d="M9 7H5a2 2 0 00-2 2v6a2 2 0 002 2h2v-4H5V9h4V7zm10 0h-4a2 2 0 00-2 2v6a2 2 0 002 2h2v-4h-2V9h4V7z"/></svg>
                        <blockquote class="text-sm text-slate-700 leading-relaxed">"{{ $quote }}"</blockquote>
                        <figcaption class="mt-5 pt-4 border-t border-slate-100">
                            <div class="font-semibold text-slate-900 text-sm">{{ $name }}</div>
                            <div class="text-xs text-slate-500">{{ $role }}</div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-emerald-700 mb-2">Common questions</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Things investors usually ask.</h2>

        <div class="mt-8 space-y-3" x-data="{ open: 0 }">
            @foreach ([
                ['Is my capital safe?', 'Your capital is allocated across many loans, never a single borrower, which spreads risk. Every loan is underwritten with CRB checks, income verification and (where applicable) GPS-tracked collateral. However, microloans are not deposits — there is real default risk, which is why we publish portfolio default rates transparently.'],
                ['What if a borrower defaults?', 'Defaults are absorbed by the pool — your individual exposure is small because each loan is one of many. We also operate a first-loss buffer funded by our origination fees, which absorbs the first 2% of pool losses before they reach investors.'],
                ['How do I withdraw?', 'Funds in your wallet (uninvested balance) can be withdrawn any time to bank, mobile money or USDC. Funds inside an active investment unlock when borrowers repay — for short-tenor pools that is within 1–3 months.'],
                ['What are the fees?', 'We charge a 1% annual management fee, deducted monthly from your gross returns. There are no deposit, withdrawal, or performance fees. All returns shown are net of fees.'],
                ['Is Kopafasta licensed?', 'Yes. Kopafasta is licensed by the Bank of Tanzania as a Tier 2 microfinance institution. We hold ISO 27001 certification for information security and our accounts are audited annually by KPMG.'],
            ] as $i => [$q, $a])
                <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                    <button @click="open = open === {{ $i }} ? null : {{ $i }}" type="button" class="w-full flex items-center justify-between gap-4 text-left px-5 py-4 hover:bg-slate-50">
                        <span class="font-semibold text-slate-900">{{ $q }}</span>
                        <svg class="w-5 h-5 text-slate-400 transition" :class="open === {{ $i }} ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                    </button>
                    <div x-cloak x-show="open === {{ $i }}" x-collapse class="px-5 pb-5 text-sm text-slate-600 leading-relaxed">{{ $a }}</div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-gradient-to-br from-emerald-600 to-emerald-700">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center text-white">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Put your savings to work today.</h2>
            <p class="mt-3 text-white/85">Open an investor account in under a minute. No fees to sign up.</p>
            <div class="mt-7 flex flex-wrap gap-3 justify-center">
                <a href="{{ route('site.register.investor') }}" class="bg-white text-emerald-700 hover:bg-emerald-50 font-semibold px-6 py-3 rounded-full">Open investor account</a>
                <a href="{{ route('site.capital-partners') }}" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white font-semibold px-6 py-3 rounded-full">Institutional? See capital programme</a>
            </div>
        </div>
    </section>

    <script>
        function returnsCalc() {
            return {
                amount: 1000000,
                months: 12,
                mix: 'balanced',
                mixes: [
                    { id: 'conservative', label: 'Conservative', yield: 0.13 },
                    { id: 'balanced',     label: 'Balanced',     yield: 0.18 },
                    { id: 'aggressive',   label: 'Aggressive',   yield: 0.22 },
                ],
                get yieldRate() { return this.mixes.find(m => m.id === this.mix)?.yield || 0.18; },
                get monthly()   { return Math.round((this.amount * this.yieldRate) / 12); },
                get total()     { return this.monthly * this.months; },
                formatTzs(v, decimals = 0) { return window.formatMoney ? window.formatMoney(v, { currency: 'TZS', decimals }) : ('TZS ' + v); },
            };
        }
    </script>
</x-site.layout>

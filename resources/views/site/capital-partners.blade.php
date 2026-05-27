<x-site.layout title="Capital partners — Kopafasta institutional programme"
                description="For banks, MFIs, DFIs, family offices and asset managers deploying USD 50K+ into Tanzania's credit market.">

    {{-- HERO --}}
    <section class="relative overflow-hidden bg-gradient-to-br from-indigo-950 via-slate-900 to-slate-900">
        <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle at 15% 20%, #6366f1 0%, transparent 45%), radial-gradient(circle at 85% 75%, #818cf8 0%, transparent 45%);"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28 grid lg:grid-cols-5 gap-12 items-center">
            <div class="text-white lg:col-span-3">
                <p class="text-xs uppercase tracking-widest text-indigo-300 mb-4">Capital partner programme</p>
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight leading-tight">
                    Institutional capital, <br><span class="text-indigo-300">deployed with discipline.</span>
                </h1>
                <p class="mt-6 text-lg text-white/80 max-w-xl">
                    For banks, MFIs, DFIs, family offices and asset managers seeking exposure to Tanzania's productive credit market — through a vehicle with institutional reporting, governance and risk controls.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('site.register.capital') }}" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-6 py-3 rounded-full shadow-lg transition">
                        Apply as partner
                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </a>
                    <a href="mailto:capital@kopafasta.com" class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 backdrop-blur text-white font-semibold px-6 py-3 rounded-full border border-white/20 transition">
                        Speak to a partner manager
                    </a>
                </div>
            </div>
            <div class="lg:col-span-2 grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-5">
                    <div class="text-2xl font-bold text-white">$50K+</div>
                    <div class="text-xs text-white/60 mt-1">Minimum commitment</div>
                </div>
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-5">
                    <div class="text-2xl font-bold text-white">17.4%</div>
                    <div class="text-xs text-white/60 mt-1">Net portfolio yield</div>
                </div>
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-5">
                    <div class="text-2xl font-bold text-white">96.3%</div>
                    <div class="text-xs text-white/60 mt-1">On-time repayment</div>
                </div>
                <div class="rounded-2xl bg-white/5 backdrop-blur border border-white/10 p-5">
                    <div class="text-2xl font-bold text-white">KPMG</div>
                    <div class="text-xs text-white/60 mt-1">Independently audited</div>
                </div>
            </div>
        </div>
    </section>

    {{-- WHO IT IS FOR --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-indigo-700 mb-2">Built for institutions</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Who partners with us.</h2>

        <div class="mt-10 grid md:grid-cols-2 lg:grid-cols-5 gap-5">
            @foreach ([
                ['🏦','Commercial banks','On- and off-balance-sheet origination through whitelabel pools.'],
                ['🏛️','Development finance institutions','Targeted SME, women &amp; agri capital with full impact reporting.'],
                ['💼','Microfinance institutions','Refinance facilities and risk-share programmes for your existing book.'],
                ['🏠','Family offices','Diversified emerging-market private credit exposure with quarterly distributions.'],
                ['📊','Asset managers &amp; funds','Wholesale facilities &amp; SPV structuring for credit funds.'],
            ] as [$icon, $title, $body])
                <div class="rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-lg p-6 transition bg-white">
                    <div class="size-11 grid place-items-center rounded-xl bg-indigo-100 text-2xl mb-3">{{ $icon }}</div>
                    <h3 class="text-base font-bold text-slate-900">{!! $title !!}</h3>
                    <p class="mt-1.5 text-xs text-slate-600 leading-relaxed">{!! $body !!}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- PROGRAMME STRUCTURE --}}
    <section class="bg-slate-50 border-t border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <p class="text-xs uppercase tracking-widest text-indigo-700 mb-2">Programme structure</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Three ways to deploy.</h2>
            <p class="mt-3 text-slate-600 max-w-2xl">Choose the structure that matches your mandate, reporting cycle, and risk-sharing preferences.</p>

            <div class="mt-10 grid md:grid-cols-3 gap-6">
                @foreach ([
                    ['Whitelabel pool','Direct investment into a Kopafasta-managed pool with your own branding and reporting cadence. Kopafasta retains 10% first-loss.','$50K – $1M','Monthly distributions'],
                    ['Co-lending facility','Side-by-side origination — you fund a fixed % of every approved loan in a defined segment. Real-time loan tape via API.','$1M – $5M','Weekly settlement'],
                    ['SPV / fund structure','Bespoke special-purpose vehicle for funds and DFIs. Customised eligibility criteria, risk sharing &amp; impact KPIs.','$5M+','Quarterly distributions'],
                ] as [$t, $d, $size, $cadence])
                    <div class="rounded-3xl bg-white border-2 border-slate-200 hover:border-indigo-500 hover:shadow-xl transition p-7">
                        <h3 class="text-xl font-bold text-slate-900">{{ $t }}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{!! $d !!}</p>
                        <dl class="mt-6 grid grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg bg-indigo-50 p-3">
                                <dt class="text-indigo-700 font-semibold uppercase tracking-wider text-[10px]">Ticket size</dt>
                                <dd class="mt-1 font-bold text-slate-900">{{ $size }}</dd>
                            </div>
                            <div class="rounded-lg bg-slate-100 p-3">
                                <dt class="text-slate-600 font-semibold uppercase tracking-wider text-[10px]">Settlement</dt>
                                <dd class="mt-1 font-bold text-slate-900">{{ $cadence }}</dd>
                            </div>
                        </dl>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- WHAT YOU GET --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
        <p class="text-xs uppercase tracking-widest text-indigo-700 mb-2">What you get</p>
        <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">An institutional-grade experience.</h2>

        <div class="mt-12 grid md:grid-cols-3 gap-6">
            @foreach ([
                ['🧑‍💼','Dedicated relationship manager','Single point of contact, weekly check-ins during deployment, quarterly business reviews.'],
                ['📡','Read-only API access','RESTful API with loan tape, repayments, defaults and portfolio NAV. Webhook events for material changes.'],
                ['📑','Custom reporting','Monthly NAV statements, audit-ready loan tapes, impact metrics (gender, geography, sector). White-labelled to your standards.'],
                ['🛡️','Risk &amp; compliance pack','Full KYC/AML on every borrower. Sanctions screening. ECL/IFRS-9 ready data feeds.'],
                ['🤝','Governance','Quarterly investment committee reviews. Annual independent audit by KPMG. Option to nominate a credit-committee observer.'],
                ['🌍','Impact reporting','Quantified social outcomes against IFC PSGs and 2X Challenge criteria — out of the box.'],
            ] as [$icon, $title, $body])
                <div class="rounded-2xl border border-slate-200 hover:border-indigo-400 hover:shadow-lg p-6 transition bg-white">
                    <div class="size-11 grid place-items-center rounded-xl bg-indigo-100 text-2xl mb-4">{{ $icon }}</div>
                    <h3 class="text-lg font-bold text-slate-900">{!! $title !!}</h3>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{!! $body !!}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ONBOARDING TIMELINE --}}
    <section class="bg-slate-50 border-t border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <p class="text-xs uppercase tracking-widest text-indigo-700 mb-2">Onboarding</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Four weeks from intro to first deployment.</h2>

            <ol class="mt-10 grid md:grid-cols-4 gap-6">
                @foreach ([
                    ['Week 1','Intro call &amp; data room','NDA signed, access to our audited financials, sample loan tape, portfolio MIS.'],
                    ['Week 2','Due diligence','Operational, credit &amp; compliance DD. Site visit (optional). Reference calls with existing partners.'],
                    ['Week 3','Term sheet','Programme structure, pricing, covenants, reporting cadence finalised &amp; signed.'],
                    ['Week 4','Legal &amp; first deployment','Facility/SPV docs executed. Initial drawdown deployed within 5 business days.'],
                ] as [$w, $t, $d])
                    <li class="relative rounded-2xl border border-slate-200 p-6 bg-white">
                        <span class="absolute -top-3 left-6 text-[11px] font-bold uppercase tracking-widest px-2 py-1 rounded-full bg-indigo-600 text-white">{{ $w }}</span>
                        <h3 class="mt-3 text-base font-bold text-slate-900">{!! $t !!}</h3>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{!! $d !!}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- GOVERNANCE / NUMBERS --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 grid lg:grid-cols-2 gap-12 items-center">
        <div>
            <p class="text-xs uppercase tracking-widest text-indigo-700 mb-2">Governance &amp; controls</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Built to pass your investment committee.</h2>
            <ul class="mt-6 space-y-3 text-sm text-slate-700">
                <li class="flex gap-3"><span class="text-indigo-700 font-bold">·</span> Independent board with majority non-executive directors</li>
                <li class="flex gap-3"><span class="text-indigo-700 font-bold">·</span> Big-4 audited financials (KPMG) — available on request</li>
                <li class="flex gap-3"><span class="text-indigo-700 font-bold">·</span> Quarterly investment-committee minutes shared with partners</li>
                <li class="flex gap-3"><span class="text-indigo-700 font-bold">·</span> ISO 27001 certified information security</li>
                <li class="flex gap-3"><span class="text-indigo-700 font-bold">·</span> Sanctions, PEP &amp; adverse-media screening on every borrower</li>
                <li class="flex gap-3"><span class="text-indigo-700 font-bold">·</span> Recovery infrastructure: 12 collection partners, 7 yards, GPS-tracked assets</li>
            </ul>
        </div>
        <div class="rounded-3xl bg-gradient-to-br from-indigo-700 to-slate-900 text-white p-8 lg:p-10">
            <h3 class="text-xl font-bold">Capital partner book</h3>
            <p class="text-sm text-white/70 mt-1">As of last quarter close.</p>
            <div class="mt-6 grid grid-cols-2 gap-5">
                <div><div class="text-3xl font-bold">8</div><div class="text-xs text-white/60 mt-1">Active institutional partners</div></div>
                <div><div class="text-3xl font-bold">$11.4M</div><div class="text-xs text-white/60 mt-1">AUM from partners</div></div>
                <div><div class="text-3xl font-bold">17.4%</div><div class="text-xs text-white/60 mt-1">Net yield delivered</div></div>
                <div><div class="text-3xl font-bold">3.1%</div><div class="text-xs text-white/60 mt-1">Portfolio default rate</div></div>
            </div>
            <a href="mailto:capital@kopafasta.com?subject=Data%20room%20request" class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-indigo-200 hover:text-white">
                Request the data room
                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
            </a>
        </div>
    </section>

    {{-- FAQ --}}
    <section class="bg-slate-50 border-t border-b border-slate-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <p class="text-xs uppercase tracking-widest text-indigo-700 mb-2">Common questions</p>
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight text-slate-900">Partner due diligence FAQ.</h2>

            <div class="mt-8 space-y-3" x-data="{ open: 0 }">
                @foreach ([
                    ['What currencies do you accept?','TZS for on-shore deployments. For cross-border partners we accept USD via correspondent banking, plus USDC and USDT for select facilities with hedged TZS exposure.'],
                    ['Can we set our own eligibility rules?','Yes — for co-lending and SPV structures we configure per-loan eligibility (sector, ticket size, geography, tenor, collateral type, borrower gender, etc.).'],
                    ['How is risk shared?','In whitelabel pools we hold a 10% first-loss. In co-lending we fund pari-passu. SPV structures support junior/senior tranching and bespoke risk-share arrangements.'],
                    ['What about FX risk?','For USD-denominated commitments we offer either unhedged TZS exposure (priced accordingly) or fully hedged via local FX partners — typically adds 4–6% to all-in cost.'],
                    ['Can we exit early?','Whitelabel pool capital amortises with the underlying loans (3–24 months). Co-lending facilities have a 90-day stop-origination notice. SPV exit terms are negotiated per deal.'],
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
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-gradient-to-br from-indigo-800 to-slate-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center text-white">
            <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">Let's build a programme together.</h2>
            <p class="mt-3 text-white/80">Apply online or reach out directly to our institutional team.</p>
            <div class="mt-7 flex flex-wrap gap-3 justify-center">
                <a href="{{ route('site.register.capital') }}" class="bg-white text-indigo-800 hover:bg-indigo-50 font-semibold px-6 py-3 rounded-full">Apply as capital partner</a>
                <a href="mailto:capital@kopafasta.com" class="bg-white/10 hover:bg-white/20 border border-white/30 text-white font-semibold px-6 py-3 rounded-full">capital@kopafasta.com</a>
            </div>
        </div>
    </section>
</x-site.layout>

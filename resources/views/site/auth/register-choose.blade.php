<x-site.layout title="Create your account — Kopafasta">
    <section class="max-w-6xl mx-auto px-4 py-16 sm:py-20">
        <div class="text-center">
            <p class="text-xs uppercase tracking-widest text-amber-600 font-semibold">Get started</p>
            <h1 class="mt-3 text-4xl sm:text-5xl font-bold tracking-tight text-gray-900">Create your account</h1>
            <p class="mt-3 text-gray-600 max-w-xl mx-auto">Pick the path that fits you. Each takes less than a minute to set up.</p>
        </div>

        <div class="mt-12 grid sm:grid-cols-2 xl:grid-cols-4 gap-5">
            <a href="{{ route('site.register.borrower') }}"
               class="group relative overflow-hidden rounded-3xl border-2 border-gray-200 hover:border-amber-500 hover:-translate-y-1 hover:shadow-xl transition-all duration-200 p-8 bg-white">
                <div class="absolute top-0 right-0 size-32 bg-amber-100 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <div class="size-14 rounded-2xl bg-gradient-to-br from-amber-400 to-amber-600 text-white grid place-items-center mb-5 shadow-md">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">I'm a borrower</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">Apply for a loan, manage repayments, and track your applications through our guided 4-step wizard.</p>
                    <ul class="mt-5 space-y-1.5 text-sm text-gray-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Personal & business loans</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Same-day decisions</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Track everything in your dashboard</li>
                    </ul>
                    <div class="mt-6 inline-flex items-center gap-1 text-sm font-bold text-amber-600 group-hover:gap-2 transition-all">
                        Continue as borrower
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </div>
                </div>
            </a>

            <a href="{{ route('site.register.partner') }}"
               class="group relative overflow-hidden rounded-3xl border-2 border-gray-200 hover:border-gray-900 hover:-translate-y-1 hover:shadow-xl transition-all duration-200 p-8 bg-white">
                <div class="absolute top-0 right-0 size-32 bg-gray-100 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <div class="size-14 rounded-2xl bg-gradient-to-br from-gray-800 to-gray-900 text-amber-400 grid place-items-center mb-5 shadow-md">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">I'm a partner</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">Partner with Kopafasta as a GPS installer, valuer, insurance provider, or yard partner.</p>
                    <ul class="mt-5 space-y-1.5 text-sm text-gray-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-gray-900" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Receive jobs across the country</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-gray-900" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Fast settlements</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-gray-900" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Mobile-first partner portal</li>
                    </ul>
                    <div class="mt-6 inline-flex items-center gap-1 text-sm font-bold text-gray-900 group-hover:gap-2 transition-all">
                        Continue as partner
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </div>
                </div>
            </a>

            <a href="{{ route('site.register.investor') }}"
               class="group relative overflow-hidden rounded-3xl border-2 border-gray-200 hover:border-emerald-600 hover:-translate-y-1 hover:shadow-xl transition-all duration-200 p-8 bg-white">
                <div class="absolute top-0 right-0 size-32 bg-emerald-100 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <div class="size-14 rounded-2xl bg-gradient-to-br from-slate-900 to-emerald-700 text-emerald-300 grid place-items-center mb-5 shadow-md">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 17l6-6 4 4 8-8M21 7h-5M21 7v5"/></svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">I'm an investor</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">Deploy capital into curated loan pools and earn monthly returns with full transparency.</p>
                    <ul class="mt-5 space-y-1.5 text-sm text-gray-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Curated funding pools</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Monthly statements & tax reports</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Auto-invest with risk preferences</li>
                    </ul>
                    <div class="mt-6 inline-flex items-center gap-1 text-sm font-bold text-emerald-700 group-hover:gap-2 transition-all">
                        Continue as investor
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </div>
                </div>
            </a>

            <a href="{{ route('site.register.capital') }}"
               class="group relative overflow-hidden rounded-3xl border-2 border-gray-200 hover:border-indigo-600 hover:-translate-y-1 hover:shadow-xl transition-all duration-200 p-8 bg-white">
                <div class="absolute top-0 right-0 size-32 bg-indigo-100 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="relative">
                    <span class="absolute -top-2 right-0 text-[10px] uppercase tracking-widest font-bold px-2 py-0.5 rounded-full bg-indigo-600 text-white">Institutional</span>
                    <div class="size-14 rounded-2xl bg-gradient-to-br from-indigo-700 to-slate-900 text-indigo-200 grid place-items-center mb-5 shadow-md">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M5 21V8l7-4 7 4v13M9 21v-6h6v6"/></svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900">Capital partner</h3>
                    <p class="mt-2 text-sm text-gray-600 leading-relaxed">For banks, MFIs, DFIs and family offices deploying $50K+ into structured pools.</p>
                    <ul class="mt-5 space-y-1.5 text-sm text-gray-600">
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Dedicated relationship manager</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Custom pool structuring & SPVs</li>
                        <li class="flex items-center gap-2"><svg class="w-4 h-4 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg> Loan-level reporting & API access</li>
                    </ul>
                    <div class="mt-6 inline-flex items-center gap-1 text-sm font-bold text-indigo-700 group-hover:gap-2 transition-all">
                        Apply as partner
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                    </div>
                </div>
            </a>
        </div>

        <p class="mt-10 text-center text-sm text-gray-600">
            Already have an account?
            <a href="{{ route('site.login') }}" class="text-amber-600 font-semibold hover:underline">Log in</a>
        </p>
    </section>
</x-site.layout>

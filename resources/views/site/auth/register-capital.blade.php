<x-site.layout title="Capital partner application — Kopafasta">
    <section class="min-h-screen grid lg:grid-cols-3 bg-slate-50">
        {{-- Sidebar --}}
        <aside class="hidden lg:flex lg:col-span-1 relative overflow-hidden bg-gradient-to-br from-indigo-900 via-slate-900 to-slate-900 text-white p-10 flex-col">
            <div class="absolute -top-32 -right-24 size-96 rounded-full bg-indigo-500/10 blur-3xl"></div>

            <a href="{{ route('site.home') }}" class="relative inline-flex items-center gap-2 font-bold text-lg">
                <span class="size-9 grid place-items-center rounded-lg bg-gradient-to-br from-indigo-400 to-indigo-600 text-white font-extrabold">K</span>
                Kopafasta
            </a>

            <div class="relative mt-12">
                <p class="text-xs uppercase tracking-widest text-indigo-300 font-semibold">Capital partner programme</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight leading-tight">Deploy institutional capital into East Africa's most disciplined credit book.</h2>
                <p class="mt-3 text-white/70 text-sm">For banks, MFIs, DFIs, family offices and asset managers looking to commit $50,000 or more.</p>
            </div>

            <ul class="relative mt-12 space-y-4 text-sm">
                <li class="flex items-start gap-3"><span class="text-indigo-300">✓</span> Dedicated relationship manager &amp; quarterly reviews</li>
                <li class="flex items-start gap-3"><span class="text-indigo-300">✓</span> Custom risk-graded pools and SPV structuring</li>
                <li class="flex items-start gap-3"><span class="text-indigo-300">✓</span> Loan-level reporting + read-only API access</li>
                <li class="flex items-start gap-3"><span class="text-indigo-300">✓</span> Audited monthly NAV statements</li>
            </ul>

            <p class="relative mt-auto text-xs text-white/40">Looking to invest as an individual? <a href="{{ route('site.register.investor') }}" class="text-indigo-300 hover:underline">Use the standard investor sign-up</a></p>
        </aside>

        {{-- Form --}}
        <div class="lg:col-span-2 flex items-start lg:items-center justify-center px-4 py-10 sm:px-10">
            <div class="w-full max-w-2xl">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-flex items-center gap-2 font-bold text-slate-900 mb-6">
                    <span class="size-9 grid place-items-center rounded-lg bg-indigo-600 text-white font-extrabold">K</span>
                    Kopafasta Capital
                </a>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-10">
                    <h1 class="text-2xl font-bold text-slate-900">Apply as a capital partner</h1>
                    <p class="mt-1 text-sm text-slate-600">Submit your details — a relationship manager will respond within one business day.</p>

                    @if ($errors->any())
                        <div class="mt-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                            <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('site.register.capital.post') }}" class="mt-6 space-y-5">
                        @csrf

                        <fieldset>
                            <legend class="text-xs font-bold uppercase tracking-widest text-indigo-700 mb-3">Organisation</legend>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Organisation name <span class="text-red-500">*</span></label>
                                    <input name="organization" value="{{ old('organization') }}" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Organisation type <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        @foreach (['bank' => 'Bank', 'mfi' => 'MFI', 'dfi' => 'DFI', 'family_office' => 'Family office', 'asset_manager' => 'Asset manager', 'other' => 'Other'] as $v => $label)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="org_type" value="{{ $v }}" {{ old('org_type', 'bank') === $v ? 'checked' : '' }} class="sr-only peer" />
                                                <div class="px-3 py-2.5 rounded-xl border-2 border-slate-200 peer-checked:border-indigo-600 peer-checked:bg-indigo-50 text-sm font-medium text-slate-700 text-center hover:border-slate-400 transition">{{ $label }}</div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">Country <span class="text-red-500">*</span></label>
                                        <input name="country" value="{{ old('country', 'Tanzania') }}" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1.5">HQ address <span class="text-slate-400 font-normal">(optional)</span></label>
                                        <input name="address" value="{{ old('address') }}" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Expected commitment <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                        @foreach (['50k_250k' => '$50K–250K', '250k_1m' => '$250K–1M', '1m_5m' => '$1M–5M', '5m_plus' => '$5M+'] as $v => $label)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="commitment_band" value="{{ $v }}" {{ old('commitment_band', '50k_250k') === $v ? 'checked' : '' }} class="sr-only peer" />
                                                <div class="px-3 py-2.5 rounded-xl border-2 border-slate-200 peer-checked:border-indigo-600 peer-checked:bg-indigo-50 text-sm font-medium text-slate-700 text-center hover:border-slate-400 transition">{{ $label }}</div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-xs font-bold uppercase tracking-widest text-indigo-700 mb-3">Primary contact</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Full name <span class="text-red-500">*</span></label>
                                    <input name="contact_name" value="{{ old('contact_name') }}" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Role / title</label>
                                    <input name="contact_role" value="{{ old('contact_role') }}" placeholder="e.g. Head of Credit" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Work email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                </div>
                                <div>
                                    <x-site.phone-input name="phone" label="Phone" :value="old('phone')" variant="rounded" :required="true"
                                        select-class="w-28 shrink-0 px-3.5 py-3 rounded-xl bg-white border border-slate-300 text-sm outline-none transition focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10"
                                        input-class="flex-1 px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none transition" />
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Anything else we should know? <span class="text-slate-400 font-normal">(optional)</span></label>
                                <textarea name="notes" rows="3" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none">{{ old('notes') }}</textarea>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-xs font-bold uppercase tracking-widest text-indigo-700 mb-3">Account security</legend>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                                    <input type="password" name="password" required minlength="8" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm password <span class="text-red-500">*</span></label>
                                    <input type="password" name="password_confirmation" required minlength="8" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-indigo-600 focus:ring-4 focus:ring-indigo-600/10 text-sm outline-none" />
                                </div>
                            </div>
                        </fieldset>

                        <div class="rounded-xl bg-indigo-50 border border-indigo-100 p-4 text-sm text-indigo-900 flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-indigo-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            Your account is created in <strong>pending</strong> status. A relationship manager will verify your organisation and unlock institutional features within 24 hours.
                        </div>

                        <button class="w-full inline-flex items-center justify-center gap-2 bg-indigo-700 hover:bg-indigo-800 text-white font-semibold py-3 px-7 rounded-full transition shadow-sm">
                            Submit application →
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>

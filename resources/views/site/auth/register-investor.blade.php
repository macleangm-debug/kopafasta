<x-site.layout title="Become an investor — Kopafasta">
    <section class="min-h-screen grid lg:grid-cols-3 bg-slate-50">
        {{-- Sidebar --}}
        <aside class="hidden lg:flex lg:col-span-1 relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-900 text-white p-10 flex-col">
            <div class="absolute -top-32 -right-24 size-96 rounded-full bg-emerald-500/10 blur-3xl"></div>

            <a href="{{ route('site.home') }}" class="relative inline-flex items-center gap-2 font-bold text-lg">
                <span class="size-9 grid place-items-center rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 text-slate-900 font-extrabold">K</span>
                Kopafasta
            </a>

            <div class="relative mt-12">
                <p class="text-xs uppercase tracking-widest text-emerald-300 font-semibold">Capital partner programme</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight leading-tight">Earn predictable returns from Tanzania's growing credit market.</h2>
                <p class="mt-3 text-white/70 text-sm">Fund curated loan pools, watch your portfolio in real time, and withdraw earnings whenever you like.</p>
            </div>

            <ul class="relative mt-12 space-y-4 text-sm">
                <li class="flex items-start gap-3"><span class="text-emerald-400">✓</span> Transparent monthly statements</li>
                <li class="flex items-start gap-3"><span class="text-emerald-400">✓</span> Risk-graded pools (low → high yield)</li>
                <li class="flex items-start gap-3"><span class="text-emerald-400">✓</span> Bank, mobile money & stablecoin support</li>
                <li class="flex items-start gap-3"><span class="text-emerald-400">✓</span> Dedicated account manager</li>
            </ul>

            <p class="relative mt-auto text-xs text-white/40">Already registered? <a href="{{ route('site.login') }}" class="text-emerald-300 hover:underline">Log in</a></p>
        </aside>

        {{-- Form --}}
        <div class="lg:col-span-2 flex items-start lg:items-center justify-center px-4 py-10 sm:px-10">
            <div class="w-full max-w-xl">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-flex items-center gap-2 font-bold text-slate-900 mb-6">
                    <span class="size-9 grid place-items-center rounded-lg bg-emerald-500 text-slate-900 font-extrabold">K</span>
                    Kopafasta Capital
                </a>

                <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-10">
                    <h1 class="text-2xl font-bold text-slate-900">Create your investor account</h1>
                    <p class="mt-1 text-sm text-slate-600">Takes less than a minute. Funding can begin immediately after sign-up.</p>

                    @if ($errors->any())
                        <div class="mt-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                            <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('site.register.investor.post') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Full name or organisation</label>
                            <input name="name" value="{{ old('name') }}" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 text-sm outline-none" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Investor type</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (['individual' => 'Individual', 'institution' => 'Institution', 'fund' => 'Fund'] as $v => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="type" value="{{ $v }}" {{ old('type', 'individual') === $v ? 'checked' : '' }} class="sr-only peer" />
                                        <div class="px-4 py-3 rounded-xl border-2 border-slate-200 peer-checked:border-emerald-600 peer-checked:bg-emerald-50 text-sm font-medium text-slate-700 text-center hover:border-slate-400 transition">{{ $label }}</div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Email</label>
                                <input type="email" name="email" value="{{ old('email') }}" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 text-sm outline-none" />
                            </div>
                            <div>
                                <x-site.phone-input name="phone" label="Phone" :value="old('phone')" variant="rounded" :required="true"
                                    select-class="w-28 shrink-0 px-3.5 py-3 rounded-xl bg-white border border-slate-300 text-sm outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10"
                                    input-class="flex-1 px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 text-sm outline-none transition" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1.5">Address <span class="text-slate-400 font-normal">(optional)</span></label>
                            <input name="address" value="{{ old('address') }}" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 text-sm outline-none" />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Password</label>
                                <input type="password" name="password" required minlength="8" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 text-sm outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1.5">Confirm password</label>
                                <input type="password" name="password_confirmation" required minlength="8" class="w-full px-3.5 py-3 rounded-xl bg-white border border-slate-300 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/10 text-sm outline-none" />
                            </div>
                        </div>

                        <x-site.turnstile action="register-investor" />

                        <button class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 px-7 rounded-full transition shadow-sm">
                            Create investor account →
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-slate-600">
                    Already have an account?
                    <a href="{{ route('site.login') }}" class="text-emerald-700 font-semibold hover:underline">Log in</a>
                </p>
            </div>
        </div>
    </section>
</x-site.layout>

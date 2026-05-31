{{-- Borrower / vendor login. Phone+PIN default; email+password secondary. --}}
<x-site.layout title="Log in — Kopafasta">
    <section class="min-h-screen grid lg:grid-cols-2 bg-gray-50">
        <aside class="hidden lg:flex relative overflow-hidden bg-gradient-to-br from-gray-900 via-gray-900 to-amber-900 text-white p-12 flex-col justify-between">
            <div class="absolute -top-32 -right-24 size-96 rounded-full bg-amber-500/20 blur-3xl"></div>
            <a href="{{ route('site.home') }}" class="relative inline-flex items-center gap-2 font-bold text-lg">
                <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                Kopafasta
            </a>
            <div class="relative">
                <h2 class="text-4xl font-bold tracking-tight leading-tight">Sign in with your<br>phone & PIN.</h2>
                <p class="mt-4 text-white/70 max-w-md">Fast, secure access designed for mobile. Email sign-in remains available as a backup.</p>
            </div>
            <p class="relative text-xs text-white/50">© {{ date('Y') }} Kopafasta</p>
        </aside>

        <div class="flex items-center justify-center px-4 py-12 sm:px-12">
            <div class="w-full max-w-md" x-data="{ method: '{{ old('auth_method', 'pin') }}' }">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-flex items-center gap-2 font-bold text-gray-900 mb-8">
                    <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                    Kopafasta
                </a>

                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Welcome back</h1>
                <p class="mt-2 text-sm text-gray-600">
                    New here? <a href="{{ route('site.register') }}" class="text-amber-600 font-semibold hover:underline">Create an account</a>
                </p>

                @if (session('status'))
                    <div class="mt-6 p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">{{ $errors->first() }}</div>
                @endif

                <div class="mt-6 inline-flex rounded-xl ring-1 ring-gray-200 bg-white p-1 text-sm w-full">
                    <button type="button" @click="method = 'pin'"
                            :class="method === 'pin' ? 'bg-amber-500 text-gray-900 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                            class="flex-1 rounded-lg py-2 font-semibold transition">Phone + PIN</button>
                    <button type="button" @click="method = 'password'"
                            :class="method === 'password' ? 'bg-amber-500 text-gray-900 shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                            class="flex-1 rounded-lg py-2 font-semibold transition">Email + Password</button>
                </div>

                <form method="POST" action="{{ route('site.login.post') }}" class="mt-6 space-y-5">
                    @csrf
                    <input type="hidden" name="auth_method" :value="method">

                    <div x-show="method === 'pin'" x-cloak>
                        <div x-data="{ prefix: '+255', localPhone: @js(old('local_phone', '')) }">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone number</label>
                            <div class="flex gap-2">
                                <select x-model="prefix" class="w-28 shrink-0 px-3 py-3 rounded-xl bg-white border border-gray-300 text-sm outline-none">
                                    <option value="+255">🇹🇿 +255</option>
                                    <option value="+254">🇰🇪 +254</option>
                                    <option value="+256">🇺🇬 +256</option>
                                </select>
                                <input type="tel" x-model="localPhone" inputmode="numeric" autocomplete="tel"
                                       :required="method === 'pin'"
                                       placeholder="7XXXXXXXX"
                                       class="flex-1 px-3 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none">
                            </div>
                            <input type="hidden" name="phone" :value="prefix + localPhone.replace(/^0+/, '').replace(/\s+/g, '')">
                            <p class="mt-1.5 text-xs text-gray-500">Enter your number without the leading zero — we add the country code automatically.</p>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-sm font-medium text-gray-700">4-digit PIN</label>
                                <a href="{{ route('site.forgot-pin') }}" class="text-xs text-amber-600 font-medium hover:underline">Forgot PIN?</a>
                            </div>
                            <input type="password" name="pin" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="one-time-code"
                                   :required="method === 'pin'"
                                   class="w-full px-3 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 text-sm tracking-[0.5em] font-mono text-center text-lg outline-none"
                                   placeholder="••••">
                        </div>
                    </div>

                    <div x-show="method === 'password'" x-cloak>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email or phone</label>
                            <input type="text" name="login" value="{{ old('login') }}" autocomplete="username"
                                   :required="method === 'password'"
                                   class="w-full px-3 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 text-sm outline-none"
                                   placeholder="you@example.com">
                        </div>
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input type="password" name="password" autocomplete="current-password"
                                   :required="method === 'password'"
                                   class="w-full px-3 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 text-sm outline-none"
                                   placeholder="Enter your password">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="trust_device" value="1" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        Trust this device for 30 days
                    </label>

                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        Keep me signed in
                    </label>

                    @if (! ($biometricEnabled ?? false))
                        <p class="text-xs text-gray-400 rounded-lg bg-gray-50 px-3 py-2">Biometric login (Face ID / fingerprint) — coming soon.</p>
                    @endif

                    <button class="w-full bg-amber-500 hover:bg-amber-400 text-gray-900 font-bold py-3 rounded-full transition shadow-sm">
                        Sign in
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-gray-200 text-center text-xs text-gray-500">
                    Staff member? <a href="{{ route('admin.login') }}" class="text-gray-700 font-semibold hover:underline">Admin console →</a>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>

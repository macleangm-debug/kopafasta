{{-- Borrower / vendor login. Separate from /admin/login. --}}
<x-site.layout title="Log in — Kopafasta">
    <section class="min-h-screen grid lg:grid-cols-2 bg-gray-50">
        {{-- Left brand panel --}}
        <aside class="hidden lg:flex relative overflow-hidden bg-gradient-to-br from-gray-900 via-gray-900 to-amber-900 text-white p-12 flex-col justify-between">
            <div class="absolute -top-32 -right-24 size-96 rounded-full bg-amber-500/20 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-24 size-96 rounded-full bg-amber-500/10 blur-3xl"></div>

            <a href="{{ route('site.home') }}" class="relative inline-flex items-center gap-2 font-bold text-lg">
                <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                Kopafasta
            </a>

            <div class="relative">
                <h2 class="text-4xl font-bold tracking-tight leading-tight">
                    Capital that moves <br> at your pace.
                </h2>
                <p class="mt-4 text-white/70 max-w-md">
                    Pick up where you left off — track your application, see your repayment schedule, or apply for a new product in minutes.
                </p>

                <ul class="mt-10 space-y-3 text-sm text-white/80">
                    <li class="flex items-center gap-3">
                        <span class="size-6 grid place-items-center rounded-full bg-amber-500/20 text-amber-300">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 10l4 4 8-8"/></svg>
                        </span>
                        Bank-grade encryption on every session
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="size-6 grid place-items-center rounded-full bg-amber-500/20 text-amber-300">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 10l4 4 8-8"/></svg>
                        </span>
                        Regulated by the Bank of Tanzania
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="size-6 grid place-items-center rounded-full bg-amber-500/20 text-amber-300">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 10l4 4 8-8"/></svg>
                        </span>
                        24/7 customer support
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/50">© {{ date('Y') }} Kopafasta. All rights reserved.</p>
        </aside>

        {{-- Right form panel --}}
        <div class="flex items-center justify-center px-4 py-12 sm:px-12">
            <div class="w-full max-w-md">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-flex items-center gap-2 font-bold text-gray-900 mb-8">
                    <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                    Kopafasta
                </a>

                <h1 class="text-3xl font-bold tracking-tight text-gray-900">Welcome back</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Don't have an account?
                    <a href="{{ route('site.register') }}" class="text-amber-600 font-semibold hover:underline">Create one</a>
                </p>

                @if ($errors->any())
                    <div class="mt-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700 flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('site.login.post') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email or phone number</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 grid place-items-center pl-3 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l9 6 9-6M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                            </span>
                            <input type="text" name="login" value="{{ old('login') }}" required autofocus
                                   class="w-full pl-10 pr-3 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none transition"
                                   placeholder="you@example.com or +2557XXXXXXXX">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-sm font-medium text-gray-700">Password</label>
                            <a href="#" class="text-xs text-amber-600 font-medium hover:underline">Forgot password?</a>
                        </div>
                        <div class="relative" x-data="{ show: false }">
                            <span class="absolute inset-y-0 left-0 grid place-items-center pl-3 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 11V7a4 4 0 10-8 0v4M5 11h14v9H5z"/></svg>
                            </span>
                            <input :type="show ? 'text' : 'password'" name="password" required
                                   class="w-full pl-10 pr-12 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none transition"
                                   placeholder="Enter your password">
                            <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 grid place-items-center pr-3 text-xs text-gray-500 hover:text-gray-700 font-medium">
                                <span x-text="show ? 'Hide' : 'Show'"></span>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-600 select-none">
                        <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                        Remember me for 30 days
                    </label>

                    <button class="w-full bg-amber-500 hover:bg-amber-400 active:bg-amber-600 text-gray-900 font-bold py-3 rounded-full transition shadow-sm">
                        Sign in
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-gray-200 text-center text-xs text-gray-500">
                    Are you a staff member?
                    <a href="{{ route('admin.login') }}" class="text-gray-700 font-semibold hover:underline">Sign in to the admin console →</a>
                </div>
            </div>
        </div>
    </section>
</x-site.layout>

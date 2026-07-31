{{-- Professional 3-step vendor registration wizard --}}
<x-site.layout title="Become a partner — Kopafasta">
    <section class="min-h-screen grid lg:grid-cols-3 bg-gray-50">
        {{-- Sidebar with steps --}}
        <aside class="hidden lg:flex lg:col-span-1 relative overflow-hidden bg-gradient-to-br from-gray-900 via-gray-900 to-gray-800 text-white p-10 flex-col">
            <div class="absolute -top-32 -right-24 size-96 rounded-full bg-amber-500/10 blur-3xl"></div>

            <a href="{{ route('site.home') }}" class="relative inline-flex items-center gap-2 font-bold text-lg">
                <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                Kopafasta
            </a>

            <div class="relative mt-12">
                <p class="text-xs uppercase tracking-widest text-amber-300 font-semibold">Partner onboarding</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight leading-tight">Partner with Tanzania's fastest lender.</h2>
                <p class="mt-3 text-white/70 text-sm">Tell us about your business — our team will review your application and onboard you within 48 hours.</p>
            </div>

            <ol class="relative mt-12 space-y-6">
                <template x-for="(label, i) in ['Business', 'Contact', 'Set password']" :key="i">
                    <li class="flex items-start gap-4">
                        <span class="size-9 grid place-items-center rounded-full text-sm font-bold flex-shrink-0 transition"
                              :class="step === i+1 ? 'bg-amber-500 text-gray-900 ring-4 ring-amber-500/30' : (step > i+1 ? 'bg-emerald-500 text-white' : 'bg-white/10 text-white/60')">
                            <span x-show="step <= i+1" x-text="i+1"></span>
                            <svg x-show="step > i+1" x-cloak class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 10l4 4 8-8"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold" :class="step >= i+1 ? 'text-white' : 'text-white/50'" x-text="label"></p>
                            <p class="text-xs text-white/50 mt-0.5" x-text="['Company & category', 'Email, phone, address', 'Secure your account'][i]"></p>
                        </div>
                    </li>
                </template>
            </ol>

            <p class="relative mt-auto text-xs text-white/40">Already registered? <a href="{{ route('site.login') }}" class="text-amber-300 hover:underline">Log in</a></p>
        </aside>

        {{-- Wizard --}}
        <div class="lg:col-span-2 flex items-start lg:items-center justify-center px-4 py-10 sm:px-10" x-data="vendorWizard({
            name:     @js(old('name', '')),
            category: @js(old('category', '')),
            email:    @js(old('email', '')),
            address:  @js(old('address', '')),
        })">
            <div class="w-full max-w-xl">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-flex items-center gap-2 font-bold text-gray-900 mb-6">
                    <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                    Kopafasta
                </a>

                <div class="lg:hidden mb-6">
                    <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                        <span>Step <span x-text="step"></span> of 3</span>
                        <span x-text="['Business', 'Contact', 'Password'][step-1]"></span>
                    </div>
                    <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-gray-900 transition-all duration-300" :style="`width: ${(step/3)*100}%`"></div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 sm:p-10">
                    @if ($errors->any())
                        <div class="mb-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                            <p class="font-medium mb-1">Please fix the following:</p>
                            <ul class="list-disc ml-5 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('site.register.vendor.post') }}">
                        @csrf

                        {{-- Step 1: Business --}}
                        <div x-show="step === 1" x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">Tell us about your business</h2>
                            <p class="mt-1 text-sm text-gray-600">This is what borrowers will see when matched with you.</p>

                            <div class="mt-6 space-y-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Company / trading name <span class="text-red-500">*</span></label>
                                    <input name="name" x-model="form.name" required placeholder="e.g. Mwananchi GPS Ltd" class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-sm outline-none transition">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Service category <span class="text-red-500">*</span></label>
                                    <div class="grid grid-cols-2 gap-2">
                                        @foreach ([
                                            'gps_installer' => ['GPS installer', '📡'],
                                            'insurance' => ['Insurance', '🛡️'],
                                            'valuer' => ['Valuer', '📋'],
                                            'yard' => ['Yard partner', '🏭'],
                                            'debt_collector' => ['Collections', '💼'],
                                            'supplier' => ['Other / supplier', '✨'],
                                        ] as $v => $meta)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="category" value="{{ $v }}" x-model="form.category" class="sr-only peer">
                                                <div class="px-4 py-3 rounded-xl border-2 border-gray-200 peer-checked:border-gray-900 peer-checked:bg-gray-900 peer-checked:text-white text-sm font-medium text-gray-700 hover:border-gray-400 transition flex items-center gap-2">
                                                    <span>{{ $meta[1] }}</span> {{ $meta[0] }}
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500">Prefer guided enrollment with business documents? <a href="{{ route('site.partners.apply', 'debt_collector') }}" class="font-semibold text-amber-700 hover:underline">Apply as a service partner</a>.</p>
                                </div>
                            </div>
                        </div>

                        {{-- Step 2: Contact --}}
                        <div x-show="step === 2" x-cloak x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">How can we reach you?</h2>
                            <p class="mt-1 text-sm text-gray-600">We'll use these to assign jobs and confirm payments.</p>

                            <div class="mt-6 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Business email <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" x-model="form.email" required placeholder="hello@yourcompany.com" class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-sm outline-none transition">
                                </div>
                                <x-site.phone-input name="phone" label="Business phone" :value="old('phone')" variant="rounded" required id="vendor-register-phone" />
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Physical address <span class="text-gray-400 font-normal">(optional)</span></label>
                                    <input name="address" x-model="form.address" placeholder="Street, ward, city" class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-sm outline-none transition">
                                </div>
                            </div>
                        </div>

                        {{-- Step 3: Password --}}
                        <div x-show="step === 3" x-cloak x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">Secure your account</h2>
                            <p class="mt-1 text-sm text-gray-600">Choose a strong password. At least 8 characters.</p>

                            <div class="mt-6 space-y-4" x-data="{ show: false }">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input :type="show ? 'text' : 'password'" name="password" required minlength="8"
                                               class="w-full pr-14 px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-sm outline-none transition">
                                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 grid place-items-center pr-3 text-xs text-gray-500 hover:text-gray-700 font-medium">
                                            <span x-text="show ? 'Hide' : 'Show'"></span>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password <span class="text-red-500">*</span></label>
                                    <input :type="show ? 'text' : 'password'" name="password_confirmation" required minlength="8"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10 text-sm outline-none transition">
                                </div>

                                <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-sm text-gray-700 flex items-start gap-2">
                                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                    Your account will be created with <strong>pending</strong> status. Our partnerships team will review and activate it within 48 hours.
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 flex items-center justify-between gap-3">
                            <button type="button" @click="prev()" x-show="step > 1" x-cloak
                                    class="px-5 py-2.5 rounded-full text-sm font-semibold text-gray-700 hover:bg-gray-100 transition">
                                ← Back
                            </button>
                            <div x-show="step === 1"></div>

                            <button type="button" @click="next()" x-show="step < 3"
                                    class="ml-auto inline-flex items-center gap-2 bg-gray-900 hover:bg-gray-800 text-white font-semibold py-3 px-7 rounded-full transition shadow-sm">
                                Continue
                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                            </button>

                            <button type="submit" x-show="step === 3" x-cloak
                                    class="ml-auto bg-amber-500 hover:bg-amber-400 active:bg-amber-600 text-gray-900 font-bold py-3 px-7 rounded-full transition shadow-sm">
                                Create vendor account →
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <script>
        function vendorWizard(initial) {
            return {
                step: 1,
                form: initial,
                next() {
                    if (this.step === 1) {
                        if (!this.form.name || !this.form.category) return alert('Please complete your business details.');
                    }
                    if (this.step === 2) {
                        const phone = document.querySelector('#vendor-register-phone input[type=\"hidden\"][name=\"phone\"]')?.value || '';
                        if (!this.form.email || phone.length < 10) return alert('Email and phone are required.');
                    }
                    if (this.step < 3) this.step++;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                prev() { if (this.step > 1) this.step--; window.scrollTo({ top: 0, behavior: 'smooth' }); },
            };
        }
    </script>
</x-site.layout>

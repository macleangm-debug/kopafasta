{{-- Professional 3-step borrower registration wizard --}}
<x-site.layout title="Register as borrower — Kopafasta">
    <section class="min-h-screen grid lg:grid-cols-3 bg-gray-50">
        {{-- Sidebar with steps --}}
        <aside class="hidden lg:flex lg:col-span-1 relative overflow-hidden bg-gradient-to-br from-gray-900 via-gray-900 to-amber-900 text-white p-10 flex-col">
            <div class="absolute -top-32 -right-24 size-96 rounded-full bg-amber-500/20 blur-3xl"></div>

            <a href="{{ route('site.home') }}" class="relative inline-flex items-center gap-2 font-bold text-lg">
                <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                Kopafasta
            </a>

            <div class="relative mt-12" x-data x-effect>
                <p class="text-xs uppercase tracking-widest text-amber-300 font-semibold">Borrower onboarding</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight leading-tight">Just a few details to get you started.</h2>
                <p class="mt-3 text-white/70 text-sm">We keep things short. You can complete KYC later in your application wizard.</p>
            </div>

            <ol class="relative mt-12 space-y-6">
                <template x-for="(label, i) in ['Country', 'Contact details', 'Password']" :key="i">
                    <li class="flex items-start gap-4">
                        <span class="size-9 grid place-items-center rounded-full text-sm font-bold flex-shrink-0 transition"
                              :class="step === i+1 ? 'bg-amber-500 text-gray-900 ring-4 ring-amber-500/30' : (step > i+1 ? 'bg-emerald-500 text-white' : 'bg-white/10 text-white/60')">
                            <span x-show="step <= i+1" x-text="i+1"></span>
                            <svg x-show="step > i+1" x-cloak class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3"><path d="M4 10l4 4 8-8"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold" :class="step >= i+1 ? 'text-white' : 'text-white/50'" x-text="label"></p>
                            <p class="text-xs text-white/50 mt-0.5" x-text="['Select your country', 'Your name & email', 'Secure your account'][i]"></p>
                        </div>
                    </li>
                </template>
            </ol>

            <p class="relative mt-auto text-xs text-white/40">Already registered? <a href="{{ route('site.login') }}" class="text-amber-300 hover:underline">Log in</a></p>
        </aside>

        {{-- Wizard --}}
        <div class="lg:col-span-2 flex items-start lg:items-center justify-center px-4 py-10 sm:px-10" x-data="borrowerWizard({
            first_name:  @js(old('first_name', '')),
            middle_name: @js(old('middle_name', '')),
            last_name:   @js(old('last_name', '')),
            email:       @js(old('email', '')),
            country:     @js(old('country', 'TZ')),
            dial_code:   @js(old('dial_code', '+255')),
            local_phone: @js(old('local_phone', '')),
            step:        @js(old('step', 1)),
            waitlist_email: @js(old('waitlist_email', '')),
            waitlist_phone: @js(old('waitlist_phone', '')),
        })">
            <div class="w-full max-w-xl">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-flex items-center gap-2 font-bold text-gray-900 mb-6">
                    <span class="size-9 grid place-items-center rounded-lg bg-amber-500 text-gray-900 font-extrabold">K</span>
                    Kopafasta
                </a>

                {{-- Mobile stepper --}}
                <div class="lg:hidden mb-6">
                    <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                        <span>Step <span x-text="step"></span> of 3</span>
                        <span x-text="['Country', 'Contact', 'Password'][step-1]"></span>
                    </div>
                    <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-500 transition-all duration-300" :style="`width: ${(step/3)*100}%`"></div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl border border-gray-200 shadow-sm p-6 sm:p-10">
                    @if ($errors->any())
                        <div class="mb-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                            <p class="font-medium mb-1">Please fix the following:</p>
                            <ul class="list-disc ml-5 space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('site.register.borrower.post') }}">
                        @csrf
                        @if (! empty($referralCode))
                            <input type="hidden" name="referral_code" value="{{ $referralCode }}">
                        @endif
                        @if (! empty($affiliateCode))
                            <input type="hidden" name="affiliate_code" value="{{ $affiliateCode }}">
                        @endif

                        {{-- Step 1: Country --}}
                        <div x-show="step === 1" x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">Select your country</h2>
                            <p class="mt-1 text-sm text-gray-600">KopaFasta is live in a small number of markets today. Pick your country to continue.</p>

                            <div class="mt-8">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                                <div class="relative">
                                    <select x-model="form.country" @change="chooseCountry(countries.find(c => c.code === form.country))"
                                            class="block w-full rounded-3xl border border-gray-300 bg-white px-4 py-3 pr-10 text-sm text-gray-900 outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10">
                                        <template x-for="country in countries" :key="country.code">
                                            <option :value="country.code" x-text="country.emoji + ' ' + country.label + ' (' + country.prefix + ')'" :selected="country.code === form.country"></option>
                                        </template>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400">
                                        <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 8l4 4 4-4"/></svg>
                                    </div>
                                </div>
                                <p class="mt-3 text-sm text-gray-600">Choose your country to see whether Kopafasta is operational there today.</p>
                            </div>

                            <div class="mt-8 grid gap-4">
                                <div class="rounded-3xl border border-gray-200 bg-gray-50 p-5">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-700">Selected country</p>
                                            <p class="mt-1 text-base text-gray-900" x-text="activeCountry.label + ' ' + activeCountry.prefix"></p>
                                        </div>
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase"
                                              :class="activeCountry.active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'"
                                              x-text="activeCountry.active ? 'Ready to register' : 'Not available yet'"></span>
                                    </div>

                                    <div class="mt-5 grid gap-3 sm:grid-cols-[160px_1fr]">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone</label>
                                            <div class="flex items-center gap-2 rounded-2xl border border-gray-300 bg-white px-3 py-3">
                                                <span class="text-base font-semibold text-gray-900" x-text="activeCountry.prefix"></span>
                                                <input type="tel" inputmode="numeric" name="local_phone" x-model="form.local_phone" @input="validatePhone()" :disabled="!activeCountry.active" placeholder="7XX XXX XXX"
                                                       class="w-full bg-transparent text-sm outline-none" />
                                            </div>
                                            <p x-show="errors.phone" x-cloak class="mt-2 text-xs text-red-600" x-text="errors.phone"></p>
                                            <p class="mt-2 text-xs text-gray-500">Enter your mobile number without the leading zero.</p>
                                        </div>
                                        <div class="rounded-3xl border border-dashed p-4"
                                             :class="activeCountry.active ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50'">
                                            <p class="text-sm font-semibold" x-text="activeCountry.active ? 'Ready for onboarding' : 'Country not yet active'">Country not yet active</p>
                                            <p class="mt-2 text-sm text-gray-600" x-text="activeCountry.active ? 'You can continue to create your account.' : 'KopaFasta is not yet operational in this country.'"></p>
                                            <template x-if="!activeCountry.active">
                                                <form method="POST" action="{{ route('site.waitlist.store') }}" class="mt-4 space-y-3">
                                                    @csrf
                                                    <input type="hidden" name="country" :value="form.country">
                                                    <input type="hidden" name="step" value="1">
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address</label>
                                                        <input type="email" name="email" x-model="waitlist_email" required placeholder="you@example.com"
                                                               class="w-full rounded-2xl border border-gray-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10" />
                                                    </div>
                                                    <div>
                                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone (optional)</label>
                                                        <input type="tel" name="phone" x-model="waitlist_phone" placeholder="+255 7XX XXXX XX"
                                                               class="w-full rounded-2xl border border-gray-300 bg-white px-3.5 py-3 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10" />
                                                    </div>
                                                    <button type="submit"
                                                            class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-gray-900 px-4 py-3 text-sm font-semibold text-white hover:bg-gray-800 transition">
                                                        Notify me when available
                                                    </button>
                                                </form>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                @if (session('waitlist_status'))
                                    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                        {{ session('waitlist_status') }}
                                    </div>
                                @endif
                            </div>

                            <input type="hidden" name="country" :value="form.country">
                            <input type="hidden" name="phone" :value="activeCountry.prefix + (form.local_phone || '').replace(/^0+/, '').replace(/\s+/g, '')">
                        </div>

                        {{-- Step 2: Personal --}}
                        <div x-show="step === 2" x-cloak x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">Tell us who you are</h2>
                            <p class="mt-1 text-sm text-gray-600">We'll use these details to build your borrower profile.</p>

                            <div class="mt-6 space-y-4">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">First name <span class="text-red-500">*</span></label>
                                        <input name="first_name" x-model="form.first_name" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none transition">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Middle name</label>
                                        <input name="middle_name" x-model="form.middle_name" class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none transition">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Last name <span class="text-red-500">*</span></label>
                                        <input name="last_name" x-model="form.last_name" required class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-300 focus:border-amber-500 focus:ring-4 focus:ring-amber-500/10 text-sm outline-none transition">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email address <span class="text-gray-400 font-normal">(optional)</span></label>
                                    <input type="email" name="email" x-model="form.email" @input="validateEmail()"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border text-sm outline-none transition"
                                           :class="errors.email ? 'border-red-400 focus:ring-red-200' : 'border-gray-300 focus:border-amber-500 focus:ring-amber-500/10'"
                                           placeholder="you@example.com">
                                    <p x-show="errors.email" x-cloak class="mt-1 text-xs text-red-600" x-text="errors.email"></p>
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
                                        <input :type="show ? 'text' : 'password'" name="password" x-model="form.password" @input="validatePasswords()" required minlength="8"
                                               class="w-full pr-14 px-3.5 py-3 rounded-xl bg-white border text-sm outline-none transition"
                                               :class="errors.password ? 'border-red-400' : 'border-gray-300 focus:border-amber-500'">
                                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 grid place-items-center pr-3 text-xs text-gray-500 font-medium">
                                            <span x-text="show ? 'Hide' : 'Show'"></span>
                                        </button>
                                    </div>
                                    <p x-show="errors.password" x-cloak class="mt-1 text-xs text-red-600" x-text="errors.password"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm password <span class="text-red-500">*</span></label>
                                    <input :type="show ? 'text' : 'password'" name="password_confirmation" x-model="form.password_confirmation" @input="validatePasswords()" required minlength="8"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border text-sm outline-none transition"
                                           :class="errors.password_confirmation ? 'border-red-400' : 'border-gray-300 focus:border-amber-500'">
                                    <p x-show="errors.password_confirmation" x-cloak class="mt-1 text-xs text-red-600" x-text="errors.password_confirmation"></p>
                                </div>

                                <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900 flex items-start gap-2">
                                    <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                    By creating an account, you agree to our <a href="#" class="underline font-medium">Terms</a> and <a href="#" class="underline font-medium">Privacy Policy</a>.
                                </div>
                            </div>
                        </div>

                        {{-- Footer nav --}}
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
                                Create account →
                            </button>
                        </div>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-gray-600 lg:hidden">
                    Already registered? <a href="{{ route('site.login') }}" class="text-amber-600 font-semibold hover:underline">Log in</a>
                </p>
            </div>
        </div>
    </section>

    <script>
        function borrowerWizard(initial) {
            return {
                step: initial.step || 1,
                countries: [
                    { code: 'TZ', label: 'Tanzania', prefix: '+255', emoji: '🇹🇿', active: true, note: 'Live now in East Africa.' },
                    { code: 'KE', label: 'Kenya', prefix: '+254', emoji: '🇰🇪', active: true, note: 'Live now in East Africa.' },
                    { code: 'UG', label: 'Uganda', prefix: '+256', emoji: '🇺🇬', active: true, note: 'Live now in East Africa.' },
                    { code: 'RW', label: 'Rwanda', prefix: '+250', emoji: '🇷🇼', active: false, note: 'Opening soon.' },
                    { code: 'BI', label: 'Burundi', prefix: '+257', emoji: '🇧🇮', active: false, note: 'Opening soon.' },
                    { code: 'SS', label: 'South Sudan', prefix: '+211', emoji: '🇸🇸', active: false, note: 'Opening soon.' },
                ],
                form: {
                    country: initial.country || 'TZ',
                    dial_code: initial.dial_code || '+255',
                    local_phone: initial.local_phone || '',
                    first_name: initial.first_name || '',
                    middle_name: initial.middle_name || '',
                    last_name: initial.last_name || '',
                    email: initial.email || '',
                    password: '',
                    password_confirmation: '',
                    waitlist_email: initial.waitlist_email || '',
                    waitlist_phone: initial.waitlist_phone || '',
                },
                errors: { phone: '', email: '', password: '', password_confirmation: '' },
                get activeCountry() {
                    return this.countries.find(c => c.code === this.form.country) ?? this.countries[0];
                },
                get canContinueStep1() {
                    return this.activeCountry.active && this.form.local_phone.trim().length >= 7 && !this.errors.phone;
                },
                validatePhone() {
                    const digits = (this.form.local_phone || '').replace(/\D/g, '');
                    this.errors.phone = digits.length >= 9 ? '' : 'Enter a valid phone number (at least 9 digits).';
                },
                validateEmail() {
                    if (!this.form.email) { this.errors.email = ''; return; }
                    this.errors.email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email) ? '' : 'Enter a valid email address.';
                },
                validatePasswords() {
                    this.errors.password = (this.form.password || '').length >= 8 ? '' : 'Password must be at least 8 characters.';
                    this.errors.password_confirmation = this.form.password === this.form.password_confirmation ? '' : 'Passwords do not match.';
                },
                chooseCountry(country) {
                    this.form.country = country.code;
                    this.form.dial_code = country.prefix;
                    if (!country.active) {
                        this.form.local_phone = '';
                    }
                },
                next() {
                    if (this.step === 1) {
                        this.validatePhone();
                        if (!this.canContinueStep1) {
                            if (!this.activeCountry.active) {
                                return alert('KopaFasta is not yet operational in this country. Please join the waitlist.');
                            }
                            return;
                        }
                    }
                    if (this.step === 2) {
                        this.validateEmail();
                        if (!this.form.first_name || !this.form.last_name) {
                            this.errors.email = this.errors.email || '';
                            return alert('Please enter your first and last name.');
                        }
                        if (this.errors.email) return;
                    }
                    if (this.step < 3) this.step++;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
                prev() {
                    if (this.step > 1) this.step--;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                },
            };
        }
    </script>
</x-site.layout>

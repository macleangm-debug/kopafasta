@php
    $prefill = $guarantorRegistration ?? null;
    $isGuarantorRegistration = $isGuarantorRegistration ?? false;
    $isGroupInviteRegistration = $isGroupInviteRegistration ?? false;
    $isInviteRegistration = $isGuarantorRegistration || $isGroupInviteRegistration;
    $initialStep = $isInviteRegistration && ! empty($prefill['local_phone']) ? 2 : (int) old('step', 1);
@endphp
{{-- Professional 3-step borrower registration wizard --}}
<x-site.layout :auth="true" :title="$isGuarantorRegistration ? brand_title(__('borrower.guarantor_invite.create_account')) : ($isGroupInviteRegistration ? brand_title(__('borrower.apply.group.register_title')) : brand_title(__('borrower.register.title')))">
    <section class="min-h-full grid lg:grid-cols-2 premium-gradient">
        <aside class="hidden lg:flex relative overflow-hidden bg-brand text-white p-12 flex-col justify-between">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_bottom_left,_#f5c842,_transparent_50%)]"></div>
            <a href="{{ route('site.home') }}" class="relative"><x-site.brand-mark variant="light" /></a>

            <div class="relative">
                @if ($isGuarantorRegistration)
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.guarantor_invite.create_account') }}</p>
                    <h2 class="mt-2 text-4xl font-bold tracking-tight leading-tight">{{ __('borrower.guarantor_invite.register_welcome') }}</h2>
                    <p class="mt-4 text-white/70 max-w-md">{{ __('borrower.guarantor_invite.register_welcome_hint') }}</p>
                @elseif ($isGroupInviteRegistration)
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.apply.group.onboarding_label') }}</p>
                    <h2 class="mt-2 text-4xl font-bold tracking-tight leading-tight">{{ __('borrower.apply.group.register_welcome') }}</h2>
                    <p class="mt-4 text-white/70 max-w-md">{{ __('borrower.apply.group.register_welcome_hint', ['leader' => $prefill['borrower_name'] ?? brand_name()]) }}</p>
                @else
                    <p class="text-xs uppercase tracking-widest text-brand-gold font-semibold">{{ __('borrower.register.aside_eyebrow') }}</p>
                    <h2 class="mt-2 text-4xl font-bold tracking-tight leading-tight">{{ __('borrower.register.aside_title') }}</h2>
                    <p class="mt-4 text-white/70 max-w-md">{{ __('borrower.register.aside_body') }}</p>
                @endif

                <ol class="mt-10 space-y-4">
                    @foreach ([
                        [__('borrower.register.step_country'), __('borrower.register.step_country_hint')],
                        [__('borrower.register.step_details'), __('borrower.register.step_details_hint')],
                        [__('borrower.register.step_password'), __('borrower.register.step_password_hint')],
                    ] as $i => [$label, $hint])
                        <li class="flex items-start gap-3">
                            <span class="size-8 grid place-items-center rounded-full text-xs font-bold flex-shrink-0 bg-white/10 text-white/70">{{ $i + 1 }}</span>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $label }}</p>
                                <p class="text-xs text-white/50">{{ $hint }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <p class="relative text-xs text-white/50">
                {{ __('borrower.register.already') }} <a href="{{ route('site.login') }}" class="text-brand-gold hover:underline">{{ __('borrower.register.login') }}</a>
                @if ($isGuarantorRegistration)
                    · <a href="{{ route('site.login', ['clear_guarantor' => 1]) }}" class="text-brand-gold hover:underline">{{ __('borrower.guarantor_invite.login_different_account') }}</a>
                @elseif ($isGroupInviteRegistration)
                    · <a href="{{ route('site.login', ['clear_group_invite' => 1]) }}" class="text-brand-gold hover:underline">{{ __('borrower.apply.group.login_different_account') }}</a>
                @endif
            </p>
        </aside>

        <div class="flex items-start lg:items-center justify-center px-4 py-8 sm:px-12 form-scroll-lock" x-data="borrowerWizard({
            first_name:  @js(old('first_name', $prefill['first_name'] ?? '')),
            middle_name: @js(old('middle_name', $prefill['middle_name'] ?? '')),
            last_name:   @js(old('last_name', $prefill['last_name'] ?? '')),
            email:       @js(old('email', '')),
            country:     @js(old('country', $prefill['country'] ?? 'TZ')),
            dial_code:   @js(old('dial_code', $prefill['dial_code'] ?? '+255')),
            local_phone: @js(old('local_phone', $prefill['local_phone'] ?? '')),
            step:        @js($initialStep),
            isGuarantor: @js($isGuarantorRegistration),
            lockIdentity: false,
            borrowerName: @js($prefill['borrower_name'] ?? ''),
            waitlist_email: @js(old('waitlist_email', '')),
            waitlist_local_phone: @js(old('waitlist_local_phone', '')),
        })">
            <div class="w-full max-w-md">
                <a href="{{ route('site.home') }}" class="lg:hidden inline-block mb-6">
                    <x-site.brand-mark size="md" />
                </a>

                <div class="lg:hidden mb-6">
                    <div class="flex items-center justify-between text-xs font-medium text-gray-500">
                        <span><span x-text="@js(__('borrower.register.step_label')) + ' ' + step + '/3'"></span></span>
                        <span x-text="[@js(__('borrower.register.step_country')), @js(__('borrower.register.step_details')), @js(__('borrower.register.step_password'))][step-1]"></span>
                    </div>
                    <div class="mt-2 h-1.5 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full bg-brand transition-all duration-300" :style="`width: ${(step/3)*100}%`"></div>
                    </div>
                </div>

                <div class="glass-card p-8 sm:p-10">
                    @if ($isGuarantorRegistration && ! empty($prefill['borrower_name']))
                        <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">
                            {{ __('borrower.guarantor_invite.register_banner', ['borrower' => $prefill['borrower_name']]) }}
                        </div>
                    @endif
                    @if (session('status'))
                        <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-6 p-3.5 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700">
                            <p class="font-medium mb-1">{{ __('borrower.register.form_errors') }}</p>
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

                        {{-- Step 1: Country & phone --}}
                        <div x-show="step === 1" x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">{{ __('borrower.register.country_phone_title') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('borrower.register.country_phone_body') }}</p>

                            <div class="mt-6 space-y-5" x-data="{ countryOpen: false }">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.country') }}</label>
                                    <button type="button" @click="countryOpen = true"
                                            class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition lg:hidden">
                                        <span class="text-xl leading-none" x-text="activeCountry.emoji || '🌍'"></span>
                                        <span class="flex-1 text-left truncate" x-text="activeCountry.label"></span>
                                        <span class="text-xs text-gray-400" x-text="activeCountry.prefix"></span>
                                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                    </button>
                                    <div class="hidden lg:block relative" @click.outside="countryOpen = false">
                                        <button type="button" @click="countryOpen = !countryOpen"
                                                class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30 transition">
                                            <span class="text-xl leading-none" x-text="activeCountry.emoji || '🌍'"></span>
                                            <span class="flex-1 text-left truncate" x-text="activeCountry.label"></span>
                                            <span class="text-xs text-gray-400" x-text="activeCountry.prefix"></span>
                                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                        </button>
                                        <div x-cloak x-show="countryOpen" x-transition
                                             class="absolute left-0 right-0 top-full mt-1 z-20 rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-56 overflow-y-auto">
                                            <template x-for="country in countries" :key="country.code">
                                                <button type="button" @click="chooseCountry(country); countryOpen = false"
                                                        class="w-full flex items-center gap-3 px-3 py-2.5 text-left text-sm hover:bg-brand-muted transition"
                                                        :class="form.country === country.code ? 'bg-brand-muted/60 text-brand font-semibold' : 'text-gray-700'">
                                                    <span class="text-xl leading-none w-7 text-center" x-text="country.emoji || '🌍'"></span>
                                                    <span class="flex-1">
                                                        <span class="block" x-text="country.label"></span>
                                                        <span class="block text-[10px] uppercase tracking-wider text-gray-400" x-text="country.code"></span>
                                                    </span>
                                                    <span class="text-xs text-gray-500" x-text="country.prefix"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <x-site.bottom-sheet title="Country" open="countryOpen">
                                        <div class="space-y-1">
                                            <template x-for="country in countries" :key="country.code">
                                                <button type="button" @click="chooseCountry(country); countryOpen = false"
                                                        class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-left text-sm transition"
                                                        :class="form.country === country.code ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'hover:bg-gray-50 text-gray-700'">
                                                    <span class="text-xl" x-text="country.emoji || '🌍'"></span>
                                                    <span class="flex-1">
                                                        <span class="block font-medium" x-text="country.label"></span>
                                                        <span class="block text-[10px] uppercase tracking-wider text-gray-400" x-text="country.code"></span>
                                                    </span>
                                                    <span class="text-xs text-gray-500" x-text="country.prefix"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </x-site.bottom-sheet>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.mobile') }}</label>
                                    <div class="flex gap-2">
                                        <span class="inline-flex items-center px-4 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm font-semibold text-brand tabular-nums shrink-0" x-text="activeCountry.prefix"></span>
                                        <input type="tel" inputmode="numeric" pattern="[0-9]*" data-digits-only data-digits-allow-spaces="1" name="local_phone" x-model="form.local_phone"
                                               @input="onPhoneInput()"
                                               autocomplete="tel-national"
                                               autocapitalize="off" autocorrect="off" spellcheck="false"
                                               data-lpignore="true" data-1p-ignore="true"
                                               :disabled="!activeCountry.active" :readonly="lockIdentity && !!form.local_phone"
                                               placeholder="712 345 678"
                                               class="flex-1 px-3.5 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-base outline-none transition">
                                    </div>
                                    <p class="mt-1.5 text-xs text-gray-500">{{ __('borrower.register.mobile_hint') }}</p>
                                </div>

                                <template x-if="!activeCountry.active">
                                    <div class="rounded-xl border border-rose-200 bg-rose-50/80 p-4">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-sm font-semibold text-gray-900">{{ __('borrower.register.unavailable_title') }}</p>
                                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase bg-rose-100 text-rose-700">
                                                {{ __('borrower.register.waitlist') }}
                                            </span>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-600">{{ __('borrower.register.unavailable_body') }}</p>

                                        <form method="POST" action="{{ route('site.waitlist.store') }}" class="mt-4 space-y-3">
                                            @csrf
                                            <input type="hidden" name="country" :value="form.country">
                                            <input type="hidden" name="step" value="1">
                                            <input type="email" name="email" x-model="waitlist_email" required placeholder="you@example.com"
                                                   class="w-full rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                                            <div class="flex gap-2">
                                                <span class="inline-flex items-center px-3.5 py-3 rounded-xl border border-gray-200 bg-gray-50 text-sm font-semibold" x-text="activeCountry.prefix"></span>
                                                <input type="tel" inputmode="numeric" pattern="[0-9]*" data-digits-only name="waitlist_local_phone" x-model="waitlist_local_phone" placeholder="712 345 678"
                                                       @input="waitlist_local_phone = String(waitlist_local_phone || '').replace(/\D/g, '')"
                                                       class="flex-1 rounded-xl border border-gray-200 bg-white px-3.5 py-3 text-sm outline-none focus:border-brand focus:ring-2 focus:ring-brand/10">
                                            </div>
                                            <input type="hidden" name="phone" :value="waitlist_local_phone ? activeCountry.prefix.replace(/\D/g, '') + waitlist_local_phone.replace(/\D/g, '').replace(/^0+/, '') : ''">
                                            <button type="submit" class="w-full rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-4 py-3 transition">
                                                {{ __('borrower.register.notify_me') }}
                                            </button>
                                        </form>
                                    </div>
                                </template>

                                @if (session('waitlist_status'))
                                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                                        {{ session('waitlist_status') }}
                                    </div>
                                @endif
                            </div>

                            <input type="hidden" name="country" :value="form.country">
                            <input type="hidden" name="phone" :value="activeCountry.prefix.replace(/\D/g, '') + (form.local_phone || '').replace(/\D/g, '').replace(/^0+/, '')">
                        </div>

                        {{-- Step 2: Personal --}}
                        <div x-show="step === 2" x-cloak x-transition>
                            <h2 class="text-2xl font-bold text-gray-900" x-text="isGuarantor ? @js(__('borrower.guarantor_invite.register_step_details')) : @js(__('borrower.register.details_title'))"></h2>
                            <p class="mt-1 text-sm text-gray-600" x-text="isGuarantor ? @js(__('borrower.guarantor_invite.register_step_details_hint')) : @js(__('borrower.register.details_body'))"></p>

                            <div class="mt-6 space-y-5">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="min-w-0">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.first_name') }} <span class="text-red-500">*</span></label>
                                        <input name="first_name" x-model="form.first_name" required autocomplete="given-name"
                                               class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm outline-none transition">
                                    </div>
                                    <div class="min-w-0">
                                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.last_name') }} <span class="text-red-500">*</span></label>
                                        <input name="last_name" x-model="form.last_name" required autocomplete="family-name"
                                               class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm outline-none transition">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.middle_name') }} <span class="text-gray-400 font-normal">{{ __('borrower.register.optional') }}</span></label>
                                    <input name="middle_name" x-model="form.middle_name" autocomplete="additional-name"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm outline-none transition">
                                </div>
                                <div class="min-w-0">
                                    <x-site.profile-select
                                        name="gender"
                                        :label="__('borrower.register.gender')"
                                        :options="['male' => __('borrower.register.male'), 'female' => __('borrower.register.female')]"
                                        :value="old('gender')"
                                        :required="true"
                                        :placeholder="__('borrower.register.gender_placeholder')"
                                        select-class="w-full px-3.5 py-3 rounded-xl bg-white border border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm outline-none transition"
                                    />
                                </div>
                                <div class="flex items-start gap-2 rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-3.5 py-2.5 text-xs text-brand">
                                    <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                    <span>{{ __('borrower.register.age_notice', ['age' => 18]) }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Step 3: Password --}}
                        <div x-show="step === 3" x-cloak x-transition>
                            <h2 class="text-2xl font-bold text-gray-900">{{ __('borrower.register.password_title') }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ __('borrower.register.password_body') }}</p>

                            <div class="mt-6 space-y-4" x-data="{ show: false }">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.password') }} <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input :type="show ? 'text' : 'password'" name="password" x-model="form.password" @input="validatePasswords()" required minlength="8"
                                               autocomplete="new-password" value=""
                                               readonly onfocus="this.removeAttribute('readonly')"
                                               data-lpignore="true" data-1p-ignore="true"
                                               class="w-full pr-14 px-3.5 py-3 rounded-xl bg-white border text-sm outline-none transition"
                                               :class="errors.password ? 'border-red-400' : 'border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10'">
                                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 grid place-items-center pr-3 text-xs text-gray-500 font-medium">
                                            <span x-text="show ? @js(__('borrower.register.hide')) : @js(__('borrower.register.show'))"></span>
                                        </button>
                                    </div>
                                    <p x-show="errors.password" x-cloak class="mt-1 text-xs text-red-600" x-text="errors.password"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.register.password_confirm') }} <span class="text-red-500">*</span></label>
                                    <input :type="show ? 'text' : 'password'" name="password_confirmation" x-model="form.password_confirmation" @input="validatePasswords()" required minlength="8"
                                           autocomplete="new-password" value=""
                                           readonly onfocus="this.removeAttribute('readonly')"
                                           data-lpignore="true" data-1p-ignore="true"
                                           class="w-full px-3.5 py-3 rounded-xl bg-white border text-sm outline-none transition"
                                           :class="errors.password_confirmation ? 'border-red-400' : 'border-gray-200 focus:border-brand focus:ring-2 focus:ring-brand/10'">
                                    <p x-show="errors.password_confirmation" x-cloak class="mt-1 text-xs text-red-600" x-text="errors.password_confirmation"></p>
                                </div>

                                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3.5 text-sm text-gray-800 leading-relaxed">
                                    <p>
                                        {!! __('borrower.register.terms_agree', [
                                            'terms' => '<a href="'.route('site.legal.terms').'" target="_blank" class="underline font-semibold text-brand whitespace-nowrap">'.e(__('borrower.register.terms')).'</a>',
                                            'privacy' => '<a href="'.route('site.legal.privacy').'" target="_blank" class="underline font-semibold text-brand whitespace-nowrap">'.e(__('borrower.register.privacy')).'</a>',
                                        ]) !!}
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Footer nav --}}
                        <div class="mt-8 flex items-center justify-between gap-3">
                            <button type="button" @click="prev()" x-show="step > 1" x-cloak
                                    class="px-5 py-2.5 rounded-full text-sm font-semibold text-gray-700 hover:bg-gray-100 transition">
                                {{ __('borrower.register.back') }}
                            </button>
                            <div x-show="step === 1"></div>

                            <button type="button" @click="next()" x-show="step === 1 && canContinueStep1" x-cloak
                                    :disabled="checkingPhone"
                                    class="ml-auto inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold py-3 px-7 rounded-xl transition shadow-sm disabled:opacity-60">
                                <span x-show="!checkingPhone">{{ __('borrower.register.continue') }}</span>
                                <span x-cloak x-show="checkingPhone">{{ __('borrower.register.checking') }}</span>
                                <svg x-show="!checkingPhone" class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                            </button>
                            <button type="button" @click="next()" x-show="step === 2"
                                    class="ml-auto inline-flex items-center gap-2 bg-brand hover:bg-brand-light text-white font-semibold py-3 px-7 rounded-xl transition shadow-sm">
                                {{ __('borrower.register.continue') }}
                                <svg class="w-4 h-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 10h12m-4-4 4 4-4 4"/></svg>
                            </button>

                            <div x-show="step === 3" x-cloak class="w-full sm:w-auto sm:ml-auto space-y-3">
                                <x-site.turnstile action="register" />
                                <button type="submit"
                                        class="w-full bg-brand-gold hover:bg-yellow-400 text-brand font-bold py-3 px-7 rounded-xl transition shadow-sm">
                                    {{ __('borrower.register.create') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-gray-600 lg:hidden">
                    {{ __('borrower.register.already') }} <a href="{{ route('site.login') }}" class="text-amber-600 font-semibold hover:underline">{{ __('borrower.register.login') }}</a>
                </p>
                @if ($isGuarantorRegistration)
                    <p class="mt-3 text-center text-sm text-gray-600 lg:hidden">
                        <a href="{{ route('site.login', ['clear_guarantor' => 1]) }}" class="text-gray-700 hover:underline">{{ __('borrower.guarantor_invite.login_different_account') }}</a>
                    </p>
                @endif
            </div>
        </div>
    </section>

    <script>
        function borrowerWizard(initial) {
            return {
                step: initial.step || 1,
                isGuarantor: initial.isGuarantor || false,
                lockIdentity: initial.lockIdentity || false,
                borrowerName: initial.borrowerName || '',
                waitlist_email: initial.waitlist_email || '',
                waitlist_local_phone: initial.waitlist_local_phone || '',
                form: {
                    country: initial.country || @js($defaultCountry ?? 'TZ'),
                    dial_code: initial.dial_code || @js($defaultDialPrefix ?? '+255'),
                    local_phone: initial.local_phone || '',
                    first_name: initial.first_name || '',
                    middle_name: initial.middle_name || '',
                    last_name: initial.last_name || '',
                    email: initial.email || '',
                    password: '',
                    password_confirmation: '',
                },
                countries: @js($registrationCountries ?? []),
                countryOpen: false,
                checkingPhone: false,
                errors: { phone: '', email: '', password: '', password_confirmation: '' },
                get activeCountry() {
                    return this.countries.find(c => c.code === this.form.country) ?? this.countries[0];
                },
                get canContinueStep1() {
                    const digits = (this.form.local_phone || '').replace(/\D/g, '').replace(/^0+/, '');
                    return this.activeCountry.active && digits.length >= 9;
                },
                fullPhone() {
                    const prefix = (this.activeCountry.prefix || '').replace(/\D/g, '');
                    const local = (this.form.local_phone || '').replace(/\D/g, '').replace(/^0+/, '');
                    return prefix + local;
                },
                onPhoneInput() {
                    this.form.local_phone = (this.form.local_phone || '').replace(/[^\d\s]/g, '');
                    this.errors.phone = '';
                },
                validatePhone() {
                    const digits = (this.form.local_phone || '').replace(/\D/g, '').replace(/^0+/, '');
                    this.errors.phone = digits.length >= 9 ? '' : @js(__('borrower.auth.phone_invalid'));
                    return ! this.errors.phone;
                },
                promptPhone() {
                    if (! this.activeCountry.active) {
                        return this.showNotice(@js(__('borrower.register.country_unavailable')));
                    }
                    this.validatePhone();
                    this.showNotice(this.errors.phone || @js(__('borrower.auth.phone_invalid')));
                },
                showNotice(message) {
                    window.dispatchEvent(new CustomEvent('open-feedback-default', {
                        detail: {
                            tone: 'warning',
                            title: @js(__('borrower.auth.phone_required_title')),
                            message: message,
                        },
                    }));
                },
                validateEmail() {
                    if (!this.form.email) { this.errors.email = ''; return; }
                    this.errors.email = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email) ? '' : 'Enter a valid email address.';
                },
                validatePasswords() {
                    const password = this.form.password || '';
                    const confirm = this.form.password_confirmation || '';
                    this.errors.password = password.length === 0 ? '' : (password.length >= 8 ? '' : @js(__('borrower.register.password_min')));
                    this.errors.password_confirmation = confirm.length === 0 ? '' : (password === confirm ? '' : @js(__('borrower.register.password_mismatch')));
                },
                chooseCountry(country) {
                    this.form.country = country.code;
                    this.form.dial_code = country.prefix;
                    if (!country.active) {
                        this.form.local_phone = '';
                    }
                },
                async next() {
                    if (this.step === 1) {
                        if (! this.canContinueStep1) {
                            return this.promptPhone();
                        }
                        this.checkingPhone = true;
                        try {
                            const response = await fetch(@js(route('site.register.check-phone')), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
                                },
                                body: JSON.stringify({ phone: this.fullPhone() }),
                            });
                            const data = await response.json();
                            if (! data.available) {
                                const loginUrl = data.redirect || @js(route('site.login'));
                                window.dispatchEvent(new CustomEvent('open-confirm-default', {
                                    detail: {
                                        tone: 'confirm',
                                        title: @js(__('borrower.auth.phone_taken_title')),
                                        message: data.message || @js(__('borrower.auth.phone_taken')),
                                        confirmLabel: @js(__('borrower.auth.phone_taken_cta')),
                                        onConfirm: () => { window.location.href = loginUrl; },
                                    },
                                }));
                                return;
                            }
                        } catch (e) {
                            return this.showNotice(@js(__('borrower.auth.phone_check_failed')));
                        } finally {
                            this.checkingPhone = false;
                        }
                    }
                    if (this.step === 2) {
                        if (!this.form.first_name || !this.form.last_name) {
                            return this.showNotice(@js(__('borrower.register.name_required')));
                        }
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

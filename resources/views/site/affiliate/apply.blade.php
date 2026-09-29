<x-site.layout :title="brand_title(__('site.affiliate_apply.title'))">
    <div class="max-w-2xl mx-auto px-4 pt-8">
        <a href="{{ route('site.affiliate') }}" class="text-sm text-brand hover:underline inline-flex items-center gap-1 mb-4">
            ← {{ __('site.affiliate.title') }}
        </a>
        <section class="relative overflow-hidden rounded-2xl kf-premium-panel mb-6">
            <div class="absolute -right-16 -top-16 h-44 w-44 rounded-full bg-brand-gold/10 pointer-events-none" aria-hidden="true"></div>
            <div class="relative px-5 sm:px-6 py-5 sm:py-6">
                <p class="text-[11px] uppercase tracking-widest text-brand-gold font-semibold">{{ brand_name() }}</p>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white mt-1">{{ __('site.affiliate_apply.title') }}</h1>
                <p class="mt-2 text-sm text-white/80 max-w-2xl leading-relaxed">{{ __('site.affiliate_apply.subtitle') }}</p>
            </div>
        </section>
    </div>

    @php
        $errorStep = 1;
        if ($errors->hasAny([
            'occupation', 'occupation_other', 'sales_experience', 'languages', 'why_affiliate',
            'previous_agent', 'previous_agent_details', 'financial_services_experience',
        ])) {
            $errorStep = 2;
        }
        if ($errors->hasAny([
            'acquisition_methods', 'channels', 'monthly_reach', 'first_10_customers', 'how_heard',
            'social_profile_url', 'registered_business', 'business_name', 'registration_number', 'tin',
            'doc_brela', 'doc_tin_certificate', 'doc_national_id_front', 'doc_national_id_back',
            'documents', 'nida_front_captured', 'nida_back_captured',
        ])) {
            $errorStep = 3;
        }
        if ($errors->hasAny([
            'declaration_accepted', 'conduct_accepted',
        ])) {
            $errorStep = 4;
        }
        if ($errors->hasAny(['date_of_birth', 'gender', 'full_name', 'email', 'phone', 'phone_alt', 'region', 'district'])) {
            $errorStep = 1;
        }

        $reachOptions = collect(['1-10', '11-30', '31-50', '51-100', '100+'])
            ->mapWithKeys(fn ($reach) => [$reach => __('site.affiliate_apply.reach_ranges.'.$reach)])
            ->all();
        $occupationOptions = [
            'shop_owner' => __('site.affiliate_apply.occupations.shop_owner'),
            'trader' => __('site.affiliate_apply.occupations.trader'),
            'farmer' => __('site.affiliate_apply.occupations.farmer'),
            'driver' => __('site.affiliate_apply.occupations.driver'),
            'teacher' => __('site.affiliate_apply.occupations.teacher'),
            'agent' => __('site.affiliate_apply.occupations.agent'),
            'content_creator' => __('site.affiliate_apply.occupations.content_creator'),
            'professional' => __('site.affiliate_apply.occupations.professional'),
            'other' => __('site.affiliate_apply.occupations.other'),
        ];
        $genderOptions = [
            'male' => __('site.affiliate_apply.gender_male'),
            'female' => __('site.affiliate_apply.gender_female'),
        ];
        $languageOptions = ['sw' => __('site.affiliate_apply.lang_sw'), 'en' => __('site.affiliate_apply.lang_en'), 'other' => __('site.affiliate_apply.lang_other')];
        $acquisitionOptions = [
            'existing_customers' => __('site.affiliate_apply.acq_existing'),
            'community' => __('site.affiliate_apply.acq_community'),
            'field_sales' => __('site.affiliate_apply.acq_field'),
            'social_media' => __('site.affiliate_apply.acq_social'),
            'professional_network' => __('site.affiliate_apply.acq_professional'),
            'workplace' => __('site.affiliate_apply.acq_workplace'),
            'other' => __('site.affiliate_apply.acq_other'),
        ];
        $channelOptions = [
            'whatsapp' => __('site.affiliate_apply.channel_whatsapp'),
            'instagram' => __('site.affiliate_apply.channel_instagram'),
            'tiktok' => __('site.affiliate_apply.channel_tiktok'),
            'facebook' => __('site.affiliate_apply.channel_facebook'),
            'physical' => __('site.affiliate_apply.channel_physical'),
            'business' => __('site.affiliate_apply.channel_business'),
            'other' => __('site.affiliate_apply.channel_other'),
        ];
        $howHeardOptions = trans('site.affiliate_apply.how_heard_options');
        if (! is_array($howHeardOptions)) {
            $howHeardOptions = [];
        }
        $conductItems = trans('site.affiliate_apply.conduct_items');
        if (! is_array($conductItems)) {
            $conductItems = array_values(array_filter([
                __('site.affiliate_apply.conduct_item_1'),
                __('site.affiliate_apply.conduct_item_2'),
                __('site.affiliate_apply.conduct_item_3'),
            ], fn ($line) => is_string($line) && $line !== '' && ! str_starts_with($line, 'site.affiliate_apply.')));
        }
        $missingLabels = [
            'full_name' => __('site.affiliate_apply.missing_full_name'),
            'date_of_birth' => __('site.affiliate_apply.missing_date_of_birth'),
            'gender' => __('site.affiliate_apply.missing_gender'),
            'email' => __('site.affiliate_apply.missing_email'),
            'phone' => __('site.affiliate_apply.missing_phone'),
            'region' => __('site.affiliate_apply.missing_region'),
            'district' => __('site.affiliate_apply.missing_district'),
            'street' => __('site.affiliate_apply.missing_street'),
            'business_name' => __('site.affiliate_apply.missing_business_name'),
            'occupation' => __('site.affiliate_apply.missing_occupation'),
            'occupation_other' => __('site.affiliate_apply.missing_occupation_other'),
            'sales_experience' => __('site.affiliate_apply.missing_sales_experience'),
            'languages' => __('site.affiliate_apply.missing_languages'),
            'why_affiliate' => __('site.affiliate_apply.missing_why'),
            'previous_agent_details' => __('site.affiliate_apply.missing_previous_agent'),
            'acquisition_methods' => __('site.affiliate_apply.missing_acquisition'),
            'channels' => __('site.affiliate_apply.missing_channels'),
            'monthly_reach' => __('site.affiliate_apply.missing_monthly_reach'),
            'how_heard' => __('site.affiliate_apply.missing_how_heard'),
            'how_heard_other' => __('site.affiliate_apply.missing_how_heard_other'),
            'social_profile_url' => __('site.affiliate_apply.missing_social_profile'),
            'first_10_customers' => __('site.affiliate_apply.missing_first_10'),
            'registered_business' => __('site.affiliate_apply.missing_registered_business'),
            'registration_number' => __('site.affiliate_apply.missing_registration_number'),
            'tin' => __('site.affiliate_apply.missing_tin'),
            'doc_brela' => __('site.affiliate_apply.missing_doc_brela'),
            'doc_tin_certificate' => __('site.affiliate_apply.missing_doc_tin'),
            'doc_national_id' => __('site.affiliate_apply.missing_nida'),
            'declaration_accepted' => __('site.affiliate_apply.missing_declaration'),
            'conduct_accepted' => __('site.affiliate_apply.missing_conduct'),
        ];
        $missingSteps = [
            'full_name' => 1, 'date_of_birth' => 1, 'gender' => 1, 'email' => 1, 'phone' => 1,
            'region' => 1, 'district' => 1, 'street' => 1, 'business_name' => 1,
            'occupation' => 2, 'occupation_other' => 2, 'sales_experience' => 2, 'languages' => 2,
            'why_affiliate' => 2, 'previous_agent_details' => 2,
            'acquisition_methods' => 3, 'channels' => 3, 'monthly_reach' => 3, 'how_heard' => 3,
            'how_heard_other' => 3, 'social_profile_url' => 3,
            'first_10_customers' => 3, 'registered_business' => 3, 'registration_number' => 3, 'tin' => 3,
            'doc_brela' => 3, 'doc_tin_certificate' => 3, 'doc_national_id' => 3,
            'declaration_accepted' => 4, 'conduct_accepted' => 4,
        ];
    @endphp

    <div class="max-w-2xl mx-auto pb-10 px-4"
         x-data="affiliateApplyForm({
            step: {{ (int) $errorStep }},
            applicant: @js(old('applicant_category', 'individual')),
            registeredBusiness: @js(old('registered_business', 'no')),
            previousAgent: @js(old('previous_agent', 'no')),
            feeRequired: @js((bool) $feeRequired),
            missingLabels: @js($missingLabels),
            missingSteps: @js($missingSteps),
            genderLabels: @js($genderOptions),
            declBodyTemplate: @js(__('site.affiliate_apply.decl_body', ['name' => '__NAME__'])),
            conductAgreeTemplate: @js(__('site.affiliate_apply.conduct_agree', ['name' => '__NAME__'])),
            submitPaymentLabel: @js(__('site.affiliate_apply.submit_payment')),
            submitApplicationLabel: @js(__('site.affiliate_apply.submit_application')),
         })">
        @if (session('status'))
            <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <p class="mb-4 text-sm text-gray-600">
            {{ __('site.affiliate_apply.track_hint') }}
            <a href="{{ route('site.partners.apply.tracking') }}" class="font-semibold text-brand hover:underline">{{ __('site.partner_apply.track_title') }}</a>
        </p>

        <form method="POST" action="{{ route('site.affiliate.apply.post') }}" enctype="multipart/form-data"
              x-ref="affiliateForm"
              @input="computeMissing()"
              @change="computeMissing()"
              class="glass-card p-6 sm:p-8 space-y-5">
            @csrf

            <div x-ref="stepRail"
                 class="flex flex-nowrap sm:grid sm:grid-cols-4 gap-1 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-1 text-xs sm:text-sm overflow-x-auto snap-x snap-mandatory scrollbar-thin">
                @foreach ([1 => __('site.affiliate_apply.section_you'), 2 => __('site.affiliate_apply.section_experience'), 3 => __('site.affiliate_apply.section_market'), 4 => __('site.affiliate_apply.section_declaration')] as $n => $label)
                    <button type="button" data-step="{{ $n }}" @click="goTo({{ $n }})"
                            class="shrink-0 snap-center sm:shrink rounded-lg py-2.5 px-3 sm:px-1 font-semibold transition whitespace-nowrap min-w-[8.5rem] sm:min-w-0"
                            :class="step === {{ $n }} ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-white'">
                        {{ $n }}. {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Step 1: About you --}}
            <div x-show="step === 1" x-cloak class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('site.affiliate.type_hint') }}</label>
                    <div class="grid sm:grid-cols-2 gap-2">
                        @foreach (['individual' => __('site.affiliate.type_individual'), 'company' => __('site.affiliate.type_company')] as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="applicant_category" value="{{ $value }}" class="peer sr-only" x-model="applicant" @checked(old('applicant_category', 'individual') === $value) required>
                                <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-3 text-center text-sm font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50 transition">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.full_name') }} <span class="text-red-500">*</span></label>
                    <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div class="grid sm:grid-cols-2 gap-4 items-start">
                    <div class="min-w-0 space-y-1.5">
                        <x-site.date-input
                            name="date_of_birth"
                            :label="__('site.affiliate_apply.date_of_birth')"
                            :value="old('date_of_birth')"
                            :required="true"
                            :max="now()->subYears(18)->format('Y-m-d')"
                            :min="'1940-01-01'"
                            :default="now()->subYears(25)->format('Y-m-d')"
                            input-class="w-full h-12 px-4 rounded-xl bg-white border border-gray-300 focus:border-brand focus:ring-2 focus:ring-brand/10 text-sm outline-none transition"
                        />
                    </div>
                    <div class="min-w-0 space-y-1.5">
                        <x-site.sheet-select
                            name="gender"
                            :label="__('site.affiliate_apply.gender')"
                            :options="$genderOptions"
                            :value="old('gender')"
                            :placeholder="__('site.affiliate_apply.select_gender')"
                            :required="true"
                            class="kf-affiliate-gender"
                        />
                    </div>
                </div>
                <p class="text-xs text-gray-500 -mt-2">{{ __('borrower.register.age_notice', ['age' => 18]) }}</p>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }} <span class="text-red-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div class="min-w-0">
                    <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone')" :required="true"
                        select-class="w-[6.75rem] shrink-0 rounded-lg border-gray-300 ring-1 ring-gray-200 px-2.5 py-2.5 text-sm focus:border-brand focus:ring-brand"
                        input-class="flex-1 min-w-0 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand" />
                </div>
                <div class="min-w-0">
                    <x-site.phone-input name="phone_alt" :label="__('site.affiliate_apply.phone_alt')" :value="old('phone_alt')" :required="false"
                        select-class="w-[6.75rem] shrink-0 rounded-lg border-gray-300 ring-1 ring-gray-200 px-2.5 py-2.5 text-sm focus:border-brand focus:ring-brand"
                        input-class="flex-1 min-w-0 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand" />
                </div>
                <div>
                    <x-site.address-fields
                        :region="old('region')"
                        :district="old('district')"
                        :ward="old('ward')"
                        :street="old('street')"
                        :showStreet="true"
                        :requireStreet="true"
                        :required="true"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ __('site.affiliate_apply.region_hint') }}</p>
                </div>
                <div class="grid sm:grid-cols-2 gap-4" x-show="applicant === 'company'" x-cloak>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.business_name') }}</label>
                        <input name="business_name" value="{{ old('business_name') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="applicant === 'company'">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.legal_name') }}</label>
                        <input name="legal_name" value="{{ old('legal_name') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.registration_number') }}</label>
                        <input name="registration_number" value="{{ old('registration_number') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.tin') }}</label>
                        <input name="tin" value="{{ old('tin') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                </div>
                <div x-show="missingOnStep(1).length" x-cloak class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">{{ __('site.affiliate_apply.step_incomplete') }}</p>
                    <ul class="mt-1 space-y-0.5">
                        <template x-for="item in missingOnStep(1)" :key="'s1-'+item.key">
                            <li class="text-sm text-amber-900" x-text="'• ' + item.label"></li>
                        </template>
                    </ul>
                </div>
                <div class="flex justify-end">
                    <button type="button" @click="goNext()"
                            class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!canLeaveStep(1)">
                        {{ __('site.partner_apply.next') }} →
                    </button>
                </div>
            </div>

            {{-- Step 2: Experience --}}
            <div x-show="step === 2" x-cloak class="space-y-5">
                <div>
                    <x-site.sheet-select
                        name="occupation"
                        :label="__('site.affiliate_apply.occupation')"
                        :options="$occupationOptions"
                        :value="old('occupation')"
                        :placeholder="__('site.affiliate_apply.select_occupation')"
                        :required="true"
                        other-name="occupation_other"
                        :other-label="__('site.affiliate_apply.occupation_other')"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.sales_experience') }}</label>
                    <textarea name="sales_experience" rows="3" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('sales_experience') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.fs_experience') }}</label>
                    <textarea name="financial_services_experience" rows="3" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('financial_services_experience') }}</textarea>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">{{ __('site.affiliate_apply.languages') }}</p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($languageOptions as $value => $label)
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="languages[]" value="{{ $value }}" class="rounded border-gray-300 text-brand" @checked(in_array($value, old('languages', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('site.affiliate_apply.previous_agent_label') }}</p>
                    <div class="grid grid-cols-2 gap-2 max-w-xs">
                        @foreach (['no' => __('site.affiliate_apply.previous_agent_no'), 'yes' => __('site.affiliate_apply.previous_agent_yes')] as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="previous_agent" value="{{ $value }}" class="peer sr-only" x-model="previousAgent" @checked(old('previous_agent', 'no') === $value) required>
                                <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-center text-sm font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div x-show="previousAgent === 'yes'" x-cloak class="mt-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.previous_agent_details') }}</label>
                        <textarea name="previous_agent_details" rows="3" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="previousAgent === 'yes'">{{ old('previous_agent_details') }}</textarea>
                    </div>
                </div>
                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-sm font-semibold text-gray-900">{{ __('site.affiliate_apply.coverage_online_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_apply.coverage_online') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.why') }}</label>
                    <textarea name="why_affiliate" rows="4" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('why_affiliate') }}</textarea>
                </div>
                <div x-show="missingOnStep(2).length" x-cloak class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">{{ __('site.affiliate_apply.step_incomplete') }}</p>
                    <ul class="mt-1 space-y-0.5">
                        <template x-for="item in missingOnStep(2)" :key="'s2-'+item.key">
                            <li class="text-sm text-amber-900" x-text="'• ' + item.label"></li>
                        </template>
                    </ul>
                </div>
                <div class="flex justify-between">
                    <button type="button" @click="goTo(1)" class="text-sm font-semibold text-gray-600">← {{ __('site.partner_apply.back') }}</button>
                    <button type="button" @click="goNext()"
                            class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!canLeaveStep(2)">
                        {{ __('site.partner_apply.next') }} →
                    </button>
                </div>
            </div>

            {{-- Step 3: Market --}}
            <div x-show="step === 3" x-cloak class="space-y-5">
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">{{ __('site.affiliate_apply.how_find') }}</p>
                    <div class="space-y-2">
                        @foreach ($acquisitionOptions as $value => $label)
                            <label class="flex items-center gap-2 text-sm rounded-xl ring-1 ring-gray-200 px-3 py-2">
                                <input type="checkbox" name="acquisition_methods[]" value="{{ $value }}" class="rounded border-gray-300 text-brand" @checked(in_array($value, old('acquisition_methods', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium text-gray-600 mb-2">{{ __('site.affiliate_apply.channels_label') }} <span class="text-red-500">*</span></p>
                    <div class="flex flex-wrap gap-3">
                        @foreach ($channelOptions as $value => $label)
                            <label class="inline-flex items-center gap-2 text-sm rounded-xl ring-1 ring-gray-200 px-3 py-2">
                                <input type="checkbox" name="channels[]" value="{{ $value }}" class="rounded border-gray-300 text-brand"
                                       @change="computeMissing()"
                                       @checked(in_array($value, old('channels', []), true))>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-show="needsSocialProfile()" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        {{ __('site.affiliate_apply.social_profile_url') }}
                        <span class="text-red-500">*</span>
                    </label>
                    <input type="url" name="social_profile_url" value="{{ old('social_profile_url') }}" placeholder="https://"
                           class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                           :required="needsSocialProfile()">
                    <p class="mt-1 text-xs text-gray-500">{{ __('site.affiliate_apply.social_profile_hint') }}</p>
                </div>
                <div>
                    <x-site.sheet-select
                        name="monthly_reach"
                        :label="__('site.affiliate_apply.monthly_reach')"
                        :options="$reachOptions"
                        :value="old('monthly_reach')"
                        :placeholder="__('site.affiliate_apply.select_reach')"
                        :required="true"
                    />
                </div>
                <div>
                    <x-site.sheet-select
                        name="how_heard"
                        :label="__('site.affiliate_apply.how_heard')"
                        :options="$howHeardOptions"
                        :value="old('how_heard')"
                        :placeholder="__('site.affiliate_apply.select_how_heard')"
                        :required="true"
                        other-name="how_heard_other"
                        :other-label="__('site.affiliate_apply.how_heard_other')"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.first_10') }} <span class="text-red-500">*</span></label>
                    <textarea name="first_10_customers" rows="4" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('first_10_customers') }}</textarea>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2">{{ __('site.affiliate_apply.registered_business_label') }}</p>
                    <div class="grid grid-cols-2 gap-2 max-w-xs">
                        @foreach (['no' => __('site.affiliate_apply.registered_business_no'), 'yes' => __('site.affiliate_apply.registered_business_yes')] as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="registered_business" value="{{ $value }}" class="peer sr-only" x-model="registeredBusiness" @checked(old('registered_business', 'no') === $value) required>
                                <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-center text-sm font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-show="registeredBusiness === 'yes' && applicant === 'individual'" x-cloak class="grid sm:grid-cols-2 gap-4 rounded-xl bg-brand-muted/30 ring-1 ring-brand/10 p-4">
                    <div class="sm:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.business_name') }}</label>
                        <input name="business_name" value="{{ old('business_name') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="registeredBusiness === 'yes' && applicant === 'individual'">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.registration_number') }}</label>
                        <input name="registration_number" value="{{ old('registration_number') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="registeredBusiness === 'yes' && applicant === 'individual'">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.tin') }}</label>
                        <input name="tin" value="{{ old('tin') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" :required="registeredBusiness === 'yes' && applicant === 'individual'">
                    </div>
                </div>

                <div class="space-y-3 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4" x-show="applicant === 'company'" x-cloak>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                    @foreach ([
                        'doc_brela' => \App\Models\PartnerApplicationDocument::DOC_TYPES['brela'],
                        'doc_tin_certificate' => \App\Models\PartnerApplicationDocument::DOC_TYPES['tin_certificate'],
                    ] as $input => $label)
                        <x-site.form-document-field :name="$input" :label="$label" :required="true" />
                    @endforeach
                </div>

                <x-site.form-nida-capture />

                <div x-show="missingOnStep(3).length" x-cloak class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3">
                    <p class="text-sm font-semibold text-amber-900">{{ __('site.affiliate_apply.step_incomplete') }}</p>
                    <ul class="mt-1 space-y-0.5">
                        <template x-for="item in missingOnStep(3)" :key="'s3-'+item.key">
                            <li class="text-sm text-amber-900" x-text="'• ' + item.label"></li>
                        </template>
                    </ul>
                </div>
                <div class="flex justify-between">
                    <button type="button" @click="goTo(2)" class="text-sm font-semibold text-gray-600">← {{ __('site.partner_apply.back') }}</button>
                    <button type="button" @click="goNext()"
                            class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="!canLeaveStep(3)">
                        {{ __('site.partner_apply.next') }} →
                    </button>
                </div>
            </div>

            {{-- Step 4: Declaration only --}}
            <div x-show="step === 4" x-cloak class="space-y-5">
                <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-3 text-sm">
                    <p class="text-sm font-semibold text-gray-900">{{ __('site.affiliate_apply.decl_title') }}</p>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div class="min-w-0">
                            <dt class="text-gray-500">{{ __('site.affiliate_apply.full_name') }}</dt>
                            <dd class="font-semibold text-gray-900 mt-0.5 break-words" x-text="formValue('full_name') || '—'"></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-gray-500">{{ __('site.affiliate_apply.date_of_birth') }}</dt>
                            <dd class="font-semibold text-gray-900 mt-0.5" x-text="formValue('date_of_birth') || '—'"></dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-gray-500">{{ __('site.affiliate_apply.gender') }}</dt>
                            <dd class="font-semibold text-gray-900 mt-0.5" x-text="displayGender()"></dd>
                        </div>
                    </dl>
                    <p class="text-sm text-gray-700 leading-relaxed"
                       x-text="personalizedDeclBody()"></p>
                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="declaration_accepted" value="1" required class="mt-1 rounded border-gray-300 text-brand" @checked(old('declaration_accepted'))>
                        <span>{{ __('site.affiliate_apply.decl_agree') }}</span>
                    </label>
                </div>

                <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-3 text-sm">
                    <p class="text-sm font-semibold text-gray-900">{{ __('site.affiliate_apply.conduct_title') }}</p>
                    <ul class="list-disc ml-5 space-y-1 text-gray-700">
                        @forelse ($conductItems as $item)
                            <li>{{ $item }}</li>
                        @empty
                            <li>{{ __('site.affiliate_apply.decl_standards') }}</li>
                            <li>{{ __('site.affiliate_apply.decl_no_fees') }}</li>
                        @endforelse
                    </ul>
                    <label class="flex items-start gap-2">
                        <input type="checkbox" name="conduct_accepted" value="1" required class="mt-1 rounded border-gray-300 text-brand" @checked(old('conduct_accepted'))>
                        <span x-text="personalizedConductAgree()"></span>
                    </label>
                </div>

                <div x-show="missingOnStep(4).length" x-cloak class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
                    <p class="text-sm font-semibold text-amber-900">{{ __('site.affiliate_apply.step_incomplete') }}</p>
                    <ul class="mt-2 space-y-1">
                        <template x-for="item in missingOnStep(4)" :key="'s4-'+item.key">
                            <li class="text-sm text-amber-900" x-text="'• ' + item.label"></li>
                        </template>
                    </ul>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" @click="goTo(3)" class="text-sm font-semibold text-gray-600 hover:text-brand">← {{ __('site.partner_apply.back') }}</button>
                    <button type="submit"
                            class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="missing.length > 0">
                        <span x-text="submitLabel()"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    @once
    @push('scripts')
    <script>
        function affiliateApplyForm(config) {
            return {
                step: Number(config.step || 1),
                applicant: config.applicant || 'individual',
                registeredBusiness: config.registeredBusiness || 'no',
                previousAgent: config.previousAgent || 'no',
                feeRequired: !!config.feeRequired,
                missing: [],
                missingLabels: config.missingLabels || {},
                missingSteps: config.missingSteps || {},
                genderLabels: config.genderLabels || {},
                declBodyTemplate: config.declBodyTemplate || '',
                conductAgreeTemplate: config.conductAgreeTemplate || '',
                submitPaymentLabel: config.submitPaymentLabel || '',
                submitApplicationLabel: config.submitApplicationLabel || '',
                formValue(name) {
                    const form = this.$refs.affiliateForm;
                    if (!form) return '';
                    const el = form.querySelector('[name="' + name + '"]');
                    if (!el) return '';
                    if (el.type === 'radio') {
                        const checked = form.querySelector('[name="' + name + '"]:checked');
                        return checked ? checked.value : '';
                    }
                    return (el.value || '').trim();
                },
                hasChecked(name) {
                    const form = this.$refs.affiliateForm;
                    return !!(form && (
                        form.querySelector('[name="' + name + '"]:checked')
                        || form.querySelector('[name="' + name + '[]"]:checked')
                    ));
                },
                checkedCount(name) {
                    const form = this.$refs.affiliateForm;
                    return form ? form.querySelectorAll('[name="' + name + '[]"]:checked').length : 0;
                },
                hasFile(name) {
                    const form = this.$refs.affiliateForm;
                    const input = form ? form.querySelector('[name="' + name + '"]') : null;
                    return !!(input && input.files && input.files.length > 0);
                },
                nidaReady() {
                    const form = this.$refs.affiliateForm;
                    if (!form) return false;
                    const frontHidden = form.querySelector('[name=nida_front_captured]')?.value === '1';
                    const backHidden = form.querySelector('[name=nida_back_captured]')?.value === '1';
                    return (frontHidden && backHidden)
                        || (this.hasFile('doc_national_id_front') && this.hasFile('doc_national_id_back'));
                },
                needsSocialProfile() {
                    const form = this.$refs.affiliateForm;
                    if (!form) return false;
                    return ['instagram', 'facebook', 'tiktok'].some((channel) => {
                        return !!form.querySelector('[name="channels[]"][value="' + channel + '"]:checked');
                    });
                },
                computeMissing() {
                    const next = [];
                    const push = (key) => {
                        if (!next.find((item) => item.key === key)) {
                            next.push({
                                key,
                                label: this.missingLabels[key] || key,
                                step: this.missingSteps[key] || 1,
                            });
                        }
                    };
                    if (!this.formValue('full_name')) push('full_name');
                    if (!this.formValue('date_of_birth')) push('date_of_birth');
                    if (!this.formValue('gender')) push('gender');
                    if (!this.formValue('email')) push('email');
                    if (!this.formValue('phone')) push('phone');
                    if (!this.formValue('region')) push('region');
                    if (!this.formValue('district')) push('district');
                    if (!this.formValue('street')) push('street');
                    if (this.applicant === 'company' && !this.formValue('business_name')) push('business_name');
                    const occ = this.formValue('occupation');
                    if (!occ) push('occupation');
                    if (occ === 'other' && !this.formValue('occupation_other')) push('occupation_other');
                    if (!this.formValue('sales_experience')) push('sales_experience');
                    if (this.checkedCount('languages') < 1) push('languages');
                    if (!this.formValue('why_affiliate')) push('why_affiliate');
                    if (this.previousAgent === 'yes' && !this.formValue('previous_agent_details')) push('previous_agent_details');
                    if (this.checkedCount('acquisition_methods') < 1) push('acquisition_methods');
                    if (this.checkedCount('channels') < 1) push('channels');
                    if (!this.formValue('monthly_reach')) push('monthly_reach');
                    const howHeard = this.formValue('how_heard');
                    if (!howHeard) push('how_heard');
                    if (howHeard === 'other' && !this.formValue('how_heard_other')) push('how_heard_other');
                    if (this.needsSocialProfile() && !this.formValue('social_profile_url')) push('social_profile_url');
                    if (!this.formValue('first_10_customers')) push('first_10_customers');
                    if (!this.registeredBusiness) push('registered_business');
                    if (this.registeredBusiness === 'yes' && this.applicant === 'individual') {
                        if (!this.formValue('business_name')) push('business_name');
                        if (!this.formValue('registration_number')) push('registration_number');
                        if (!this.formValue('tin')) push('tin');
                    }
                    if (this.applicant === 'company') {
                        if (!this.hasFile('doc_brela')) push('doc_brela');
                        if (!this.hasFile('doc_tin_certificate')) push('doc_tin_certificate');
                    }
                    if (!this.nidaReady()) push('doc_national_id');
                    if (!this.hasChecked('declaration_accepted')) push('declaration_accepted');
                    if (!this.hasChecked('conduct_accepted')) push('conduct_accepted');
                    this.missing = next;
                },
                jumpTo(item) {
                    this.goTo(item.step);
                },
                missingOnStep(stepNo) {
                    return this.missing.filter((item) => Number(item.step) === Number(stepNo));
                },
                canLeaveStep(stepNo) {
                    return this.missingOnStep(stepNo).length === 0;
                },
                goTo(target) {
                    const next = Number(target);
                    if (!next || next === this.step) return;
                    if (next < this.step) {
                        this.step = next;
                        this.$nextTick(() => this.scrollStepIntoView());
                        return;
                    }
                    this.computeMissing();
                    for (let s = 1; s < next; s++) {
                        if (!this.canLeaveStep(s)) {
                            this.step = s;
                            this.$nextTick(() => this.scrollStepIntoView());
                            return;
                        }
                    }
                    this.step = next;
                    this.$nextTick(() => this.scrollStepIntoView());
                },
                goNext() {
                    this.goTo(this.step + 1);
                },
                scrollStepIntoView() {
                    const rail = this.$refs.stepRail;
                    const active = rail ? rail.querySelector('[data-step="' + this.step + '"]') : null;
                    if (active) {
                        active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                    }
                },
                displayGender() {
                    const value = this.formValue('gender');
                    return this.genderLabels[value] || value || '—';
                },
                personalizedDeclBody() {
                    return String(this.declBodyTemplate || '').replaceAll('__NAME__', this.formValue('full_name') || '—');
                },
                personalizedConductAgree() {
                    return String(this.conductAgreeTemplate || '').replaceAll('__NAME__', this.formValue('full_name') || '—');
                },
                submitLabel() {
                    return this.feeRequired ? this.submitPaymentLabel : this.submitApplicationLabel;
                },
                init() {
                    this.$watch('step', () => this.$nextTick(() => this.scrollStepIntoView()));
                    this.$watch('applicant', () => this.computeMissing());
                    this.$watch('registeredBusiness', () => this.computeMissing());
                    this.$watch('previousAgent', () => this.computeMissing());
                    const recompute = () => this.$nextTick(() => this.computeMissing());
                    window.addEventListener('kf-document-file', recompute);
                    window.addEventListener('kf-document-pages-ready', recompute);
                    window.addEventListener('kf-date-changed', recompute);
                    this.$nextTick(() => this.computeMissing());
                },
            };
        }
    </script>
    @endpush
    @endonce
</x-site.layout>

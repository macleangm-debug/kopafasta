<x-site.layout :title="brand_title(__('site.affiliate_apply.title'))">
    <section class="bg-brand text-white">
        <div class="max-w-2xl mx-auto px-4 py-10">
            <a href="{{ route('site.affiliate') }}" class="text-sm text-white/70 hover:text-white inline-flex items-center gap-1 mb-4">
                ← {{ __('site.affiliate.title') }}
            </a>
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-2">{{ brand_name() }}</p>
            <h1 class="text-3xl font-bold tracking-tight">{{ __('site.affiliate_apply.title') }}</h1>
            <p class="text-sm text-white/80 mt-2">{{ __('site.affiliate_apply.subtitle') }}</p>
        </div>
    </section>

    @php
        $errorStep = 1;
        if ($errors->hasAny(['occupation','sales_experience','languages','why_affiliate'])) {
            $errorStep = 2;
        }
        if ($errors->hasAny(['acquisition_methods','monthly_reach','first_10_customers'])) {
            $errorStep = 3;
        }
        if ($errors->hasAny(['declaration_accurate','declaration_standards','declaration_no_fees','declaration_not_employment','doc_brela','doc_tin_certificate','doc_national_id_front','doc_national_id_back','documents'])) {
            $errorStep = 4;
        }
        $reachOptions = ['1-10','11-30','31-50','51-100','100+'];
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
    @endphp

    <div class="max-w-2xl mx-auto py-10 px-4 -mt-6"
         x-data="{
            step: {{ $errorStep }},
            applicant: @js(old('applicant_category', 'individual')),
         }">
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

        <form method="POST" action="{{ route('site.affiliate.apply.post') }}" enctype="multipart/form-data" class="glass-card p-6 sm:p-8 space-y-5"
              @submit.prevent="window.confirmForm($el, {
                  title: @js(__('site.affiliate_apply.confirm_title')),
                  message: applicant === 'company'
                      ? @js(__('site.affiliate_apply.confirm_message_company'))
                      : @js(__('site.affiliate_apply.confirm_message_individual')),
                  confirmLabel: @js(__('site.affiliate_apply.confirm_button')),
                  tone: 'confirm',
              })">
            @csrf

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-1 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-1 text-xs sm:text-sm">
                @foreach ([1 => __('site.affiliate_apply.section_you'), 2 => __('site.affiliate_apply.section_experience'), 3 => __('site.affiliate_apply.section_market'), 4 => __('site.affiliate_apply.section_declaration')] as $n => $label)
                    <button type="button" @click="step = {{ $n }}"
                            class="rounded-lg py-2.5 px-1 font-semibold transition"
                            :class="step === {{ $n }} ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-white'">
                        {{ $n }}. {{ $label }}
                    </button>
                @endforeach
            </div>

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
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.full_name') }}</label>
                    <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div class="min-w-0">
                    <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone')" :required="true"
                        select-class="w-[6.75rem] shrink-0 rounded-lg border-gray-300 ring-1 ring-gray-200 px-2.5 py-2.5 text-sm focus:border-brand focus:ring-brand"
                        input-class="flex-1 min-w-0 w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.region') }}</label>
                    <select name="region" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('site.affiliate_apply.select_region') }}</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
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
                <div class="flex justify-end">
                    <button type="button" @click="step = 2" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                        {{ __('site.partner_apply.next') }} →
                    </button>
                </div>
            </div>

            <div x-show="step === 2" x-cloak class="space-y-5">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.occupation') }}</label>
                    <input name="occupation" value="{{ old('occupation') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
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
                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 px-4 py-3">
                    <p class="text-sm font-semibold text-gray-900">{{ __('site.affiliate_apply.coverage_online_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('site.affiliate_apply.coverage_online') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.why') }}</label>
                    <textarea name="why_affiliate" rows="4" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('why_affiliate') }}</textarea>
                </div>
                <div class="flex justify-between">
                    <button type="button" @click="step = 1" class="text-sm font-semibold text-gray-600">← {{ __('site.partner_apply.back') }}</button>
                    <button type="button" @click="step = 3" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.partner_apply.next') }} →</button>
                </div>
            </div>

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
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.monthly_reach') }}</label>
                    <select name="monthly_reach" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('site.affiliate_apply.select_reach') }}</option>
                        @foreach ($reachOptions as $reach)
                            <option value="{{ $reach }}" @selected(old('monthly_reach') === $reach)>{{ __('site.affiliate_apply.reach_ranges.'.$reach) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.first_10') }}</label>
                    <textarea name="first_10_customers" rows="4" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('first_10_customers') }}</textarea>
                </div>
                <div class="flex justify-between">
                    <button type="button" @click="step = 2" class="text-sm font-semibold text-gray-600">← {{ __('site.partner_apply.back') }}</button>
                    <button type="button" @click="step = 4" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.partner_apply.next') }} →</button>
                </div>
            </div>

            <div x-show="step === 4" x-cloak class="space-y-5">
                <div class="space-y-3 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4" x-show="applicant === 'company'" x-cloak>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                    @foreach ([
                        'doc_brela' => \App\Models\PartnerApplicationDocument::DOC_TYPES['brela'],
                        'doc_tin_certificate' => \App\Models\PartnerApplicationDocument::DOC_TYPES['tin_certificate'],
                    ] as $input => $label)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }} <span class="text-red-500">*</span></label>
                            <x-site.single-image-document-upload :name="$input" facing="environment" :input-host-id="$input.'-host'" />
                        </div>
                    @endforeach
                </div>
                <div class="space-y-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.registrant_id') }}</p>
                    @foreach ([
                        'doc_national_id_front' => \App\Models\PartnerApplicationDocument::DOC_TYPES['national_id_front'],
                        'doc_national_id_back' => \App\Models\PartnerApplicationDocument::DOC_TYPES['national_id_back'],
                    ] as $input => $label)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }} <span class="text-red-500">*</span></label>
                            <x-site.single-image-document-upload :name="$input" facing="environment" :input-host-id="$input.'-host'" />
                        </div>
                    @endforeach
                </div>
                <div class="space-y-3 rounded-xl ring-1 ring-gray-200 p-4 text-sm">
                    <label class="flex items-start gap-2"><input type="checkbox" name="declaration_accurate" value="1" required class="mt-1 rounded border-gray-300 text-brand" @checked(old('declaration_accurate'))> {{ __('site.affiliate_apply.decl_accurate') }}</label>
                    <label class="flex items-start gap-2"><input type="checkbox" name="declaration_standards" value="1" required class="mt-1 rounded border-gray-300 text-brand" @checked(old('declaration_standards'))> {{ __('site.affiliate_apply.decl_standards') }}</label>
                    <label class="flex items-start gap-2"><input type="checkbox" name="declaration_no_fees" value="1" required class="mt-1 rounded border-gray-300 text-brand" @checked(old('declaration_no_fees'))> {{ __('site.affiliate_apply.decl_no_fees') }}</label>
                    <label class="flex items-start gap-2"><input type="checkbox" name="declaration_not_employment" value="1" required class="mt-1 rounded border-gray-300 text-brand" @checked(old('declaration_not_employment'))> {{ __('site.affiliate_apply.decl_not_employment') }}</label>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" @click="step = 3" class="text-sm font-semibold text-gray-600 hover:text-brand">← {{ __('site.partner_apply.back') }}</button>
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm">{{ __('site.affiliate_apply.submit') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-site.layout>

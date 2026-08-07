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

    <div class="max-w-2xl mx-auto py-10 px-4 -mt-6"
         x-data="{
            step: {{ $errors->hasAny(['doc_brela','doc_tin_certificate','doc_business_licence','doc_national_id_front','doc_national_id_back','documents']) ? 2 : 1 }},
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

            <div class="flex gap-1 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-1 text-sm">
                @foreach ([1 => __('site.partner_apply.tab_contact'), 2 => __('site.partner_apply.tab_documents')] as $n => $label)
                    <button type="button" @click="step = {{ $n }}"
                            class="flex-1 rounded-lg py-2.5 font-semibold transition"
                            :class="step === {{ $n }} ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-white'">
                        {{ $n }}. {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Step 1: Details --}}
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
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="min-w-0">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.business_name') }}</label>
                        <input name="business_name" value="{{ old('business_name') }}" class="w-full min-w-0 rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm"
                               :required="applicant === 'company'">
                    </div>
                    <div class="min-w-0">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.region') }}</label>
                        <select name="region" class="w-full min-w-0 rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                            <option value="">{{ __('site.affiliate_apply.select_region') }}</option>
                            @foreach ($regions as $region)
                                <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.message') }}</label>
                    <textarea name="message" rows="4" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.affiliate_apply.message_placeholder') }}">{{ old('message') }}</textarea>
                </div>

                <div class="space-y-3 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4" x-show="applicant === 'company'" x-cloak>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
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
                </div>

                <div class="flex justify-end">
                    <button type="button" @click="step = 2" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                        {{ __('site.partner_apply.next') }} →
                    </button>
                </div>
            </div>

            {{-- Step 2: Documents --}}
            <div x-show="step === 2" x-cloak class="space-y-5">
                <div class="space-y-3 rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4" x-show="applicant === 'company'" x-cloak>
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                    <p class="text-xs text-gray-500">{{ __('site.partner_apply.documents_hint_company') }}</p>
                    @foreach ([
                        'doc_brela' => \App\Models\PartnerApplicationDocument::DOC_TYPES['brela'],
                        'doc_tin_certificate' => \App\Models\PartnerApplicationDocument::DOC_TYPES['tin_certificate'],
                        'doc_business_licence' => \App\Models\PartnerApplicationDocument::DOC_TYPES['business_licence'],
                    ] as $input => $label)
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }} <span class="text-red-500">*</span></label>
                            <x-site.single-image-document-upload :name="$input" facing="environment" :input-host-id="$input.'-host'" />
                        </div>
                    @endforeach
                </div>

                <div class="space-y-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.registrant_id') }}</p>
                    <p class="text-xs text-gray-500" x-text="applicant === 'individual' ? @js(__('site.partner_apply.documents_hint_individual')) : @js(__('site.partner_apply.documents_hint_company'))"></p>
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

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <button type="button" @click="step = 1" class="text-sm font-semibold text-gray-600 hover:text-brand">
                        ← {{ __('site.partner_apply.back') }}
                    </button>
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm">
                        {{ __('site.affiliate_apply.submit') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
</x-site.layout>

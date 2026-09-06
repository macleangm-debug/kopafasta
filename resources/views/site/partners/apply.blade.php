<x-site.layout :title="brand_title(__('site.partner_apply.title', ['type' => $categoryLabel]))">
    <section class="bg-brand text-white">
        <div class="max-w-2xl mx-auto px-4 py-10">
            <a href="{{ route('site.partners') }}" class="text-sm text-white/70 hover:text-white inline-flex items-center gap-1 mb-4">
                ← {{ __('site.partners.title') }}
            </a>
            <p class="text-xs uppercase tracking-widest text-brand-gold mb-2">{{ brand_name() }}</p>
            <h1 class="text-3xl font-bold tracking-tight">{{ __('site.partner_apply.title', ['type' => $categoryLabel]) }}</h1>
            <p class="text-sm text-white/80 mt-2">{{ __('site.partner_apply.subtitle') }}</p>
        </div>
    </section>

    <div class="max-w-2xl mx-auto py-10 px-4 -mt-6"
         x-data="{
            step: {{ $errors->hasAny(['doc_brela','doc_tin_certificate','doc_business_licence','doc_national_id_front','doc_national_id_back','documents','registration_number','tin']) ? 3 : ($errors->hasAny(['region','coverage_regions']) ? 2 : 1) }},
            category: @js(old('partner_category', $category)),
            applicant: @js(old('applicant_category', ($category === 'valuer' && old('applicant_category') === 'individual') ? 'individual' : 'company')),
            changingType: false,
            labels: @js(collect($categories)->mapWithKeys(fn ($label, $key) => [$key => __('site.partner_apply.types.'.$key)])->all()),
            get allowsIndividual() { return this.category === 'valuer'; },
            setCategory(value) {
                this.category = value;
                if (value !== 'valuer') {
                    this.applicant = 'company';
                }
            },
         }">
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Chosen partner type (not a full type grid) --}}
        <div class="glass-card p-4 sm:p-5 mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold">{{ __('site.partner_apply.partner_type') }}</p>
                <p class="text-lg font-bold text-gray-900" x-text="labels[category] || category"></p>
            </div>
            <button type="button" @click="changingType = !changingType"
                    class="text-sm font-semibold text-brand hover:underline">
                <span x-show="!changingType">{{ __('site.partner_apply.change_type') }}</span>
                <span x-show="changingType" x-cloak>{{ __('site.partner_apply.hide_types') }}</span>
            </button>
            <div x-show="changingType" x-cloak class="w-full grid sm:grid-cols-2 gap-2 pt-2 border-t border-gray-100">
                @foreach ($categories as $value => $label)
                    <button type="button" @click="setCategory(@js($value)); changingType = false"
                            class="rounded-xl ring-1 px-3 py-2.5 text-center text-sm font-semibold transition"
                            :class="category === @js($value) ? 'ring-brand bg-brand-muted/50' : 'ring-gray-200 hover:bg-gray-50'">
                        {{ __('site.partner_apply.types.'.$value) }}
                    </button>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('site.partners.apply.post') }}" enctype="multipart/form-data" class="glass-card p-6 sm:p-8 space-y-5"
              data-saving-message="{{ __('borrower.profile.uploading_documents') }}"
              @submit.prevent="
                  const roles = [...$el.querySelectorAll('input[name=\'requested_roles[]\']:checked')].map((el) => el.value);
                  const caps = [];
                  if (roles.includes('debt_collector')) caps.push(@js(__('site.partner_apply.capability_repossession')));
                  if (roles.includes('auctioneer')) caps.push(@js(__('site.partner_apply.capability_auctioning')));
                  const typeLabel = labels[category] || category;
                  const message = category === 'debt_collector'
                      ? (@js(__('site.partner_apply.confirm_message_roles'))).replace(':type', typeLabel).replace(':roles', caps.length ? caps.join(' + ') : @js(__('site.partner_apply.confirm_no_roles')))
                      : (@js(__('site.partner_apply.confirm_message'))).replace(':type', typeLabel);
                  window.confirmForm($el, {
                      title: @js(__('site.partner_apply.confirm_title')),
                      message,
                      confirmLabel: @js(__('site.partner_apply.confirm_button')),
                      tone: 'confirm',
                  });
              ">
            @csrf
            <input type="hidden" name="partner_category" :value="category">

            {{-- Tabs --}}
            <div class="flex gap-1 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-1 text-sm overflow-x-auto">
                @foreach ([
                    1 => __('site.partner_apply.tab_contact'),
                    2 => __('site.partner_apply.tab_location'),
                    3 => __('site.partner_apply.tab_documents'),
                    4 => __('site.partner_apply.tab_review'),
                ] as $n => $label)
                    <button type="button" @click="step = {{ $n }}"
                            class="flex-1 min-w-[4.5rem] rounded-lg py-2.5 font-semibold transition whitespace-nowrap"
                            :class="step === {{ $n }} ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-white'">
                        {{ $n }}. {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Step 1: Contact --}}
            <div x-show="step === 1" x-cloak class="space-y-5">
                <input type="hidden" name="applicant_category" :value="allowsIndividual ? applicant : 'company'">

                {{-- Applicant type only for valuers; all other partner types are company-only. --}}
                <template x-if="allowsIndividual">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('site.partner_apply.applicant_type') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['individual' => __('site.affiliate.type_individual'), 'company' => __('site.affiliate.type_company')] as $value => $label)
                                <button type="button" @click="applicant = @js($value)"
                                        class="rounded-xl ring-1 px-3 py-3 text-center text-sm font-semibold transition"
                                        :class="applicant === @js($value) ? 'ring-brand bg-brand-muted/50' : 'ring-gray-200 hover:bg-gray-50'">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <p class="mt-2 text-xs text-gray-500" x-show="applicant === 'individual'" x-cloak>{{ __('site.partner_apply.individual_hint') }}</p>
                    </div>
                </template>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.contact_name') }}</label>
                    <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.contact_name_placeholder') }}">
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

                <div x-show="category === 'debt_collector'" x-cloak class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 space-y-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.capabilities_title') }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('site.partner_apply.capabilities_hint') }}</p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <label class="flex items-start gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3 cursor-pointer hover:ring-brand/40 transition">
                            <input type="checkbox" name="requested_roles[]" value="debt_collector"
                                   @checked(in_array('debt_collector', old('requested_roles', ['debt_collector', 'auctioneer']), true))
                                   class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ __('site.partner_apply.capability_repossession') }}</span>
                                <span class="block text-xs text-gray-500 mt-0.5">{{ __('site.partner_apply.capability_repossession_hint') }}</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-3 rounded-xl bg-white ring-1 ring-gray-200 px-3 py-3 cursor-pointer hover:ring-brand/40 transition">
                            <input type="checkbox" name="requested_roles[]" value="auctioneer"
                                   @checked(in_array('auctioneer', old('requested_roles', ['debt_collector', 'auctioneer']), true))
                                   class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ __('site.partner_apply.capability_auctioning') }}</span>
                                <span class="block text-xs text-gray-500 mt-0.5">{{ __('site.partner_apply.capability_auctioning_hint') }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <template x-if="!allowsIndividual || applicant === 'company'">
                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 space-y-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.trading_name') }}</label>
                            <input name="business_name" value="{{ old('business_name') }}" required
                                   class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.trading_name_placeholder') }}">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.legal_name') }}</label>
                            <input name="legal_name" value="{{ old('legal_name') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.legal_name_hint') }}">
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.registration_number') }}</label>
                                <input name="registration_number" value="{{ old('registration_number') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.registration_number_placeholder') }}">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.tin') }}</label>
                                <input name="tin" value="{{ old('tin') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.tin_placeholder') }}">
                            </div>
                        </div>
                    </div>
                </template>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.message') }}</label>
                    <textarea name="message" rows="3" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.message_placeholder') }}">{{ old('message') }}</textarea>
                </div>

                <div class="flex justify-end">
                    <button type="button" @click="step = 2" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.partner_apply.next') }} →</button>
                </div>
            </div>

            {{-- Step 2: Location --}}
            <div x-show="step === 2" x-cloak class="space-y-5">
                <x-site.address-fields
                    :region="old('region')"
                    :district="old('district')"
                    :ward="old('ward')"
                    :street="old('street', old('address'))"
                />
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.coverage') }}</label>
                    <div class="grid sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-3">
                        @foreach ($regions as $region)
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="coverage_regions[]" value="{{ $region }}"
                                       @checked(in_array($region, old('coverage_regions', []), true))
                                       class="rounded border-gray-300 text-brand focus:ring-brand">
                                <span>{{ $region }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="mt-1 text-[11px] text-gray-500">{{ __('site.partner_apply.coverage_hint') }}</p>
                </div>
                <div class="flex justify-between">
                    <button type="button" @click="step = 1" class="text-sm font-semibold text-gray-600 hover:text-brand">← {{ __('site.partner_apply.back') }}</button>
                    <button type="button" @click="step = 3" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.partner_apply.next') }} →</button>
                </div>
            </div>

            {{-- Step 3: Documents --}}
            <div x-show="step === 3" x-cloak class="space-y-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-700">{{ __('site.partner_apply.documents_section') }}</p>
                <p class="text-xs text-gray-500" x-text="allowsIndividual && applicant === 'individual' ? @js(__('site.partner_apply.documents_hint_individual')) : @js(__('site.partner_apply.documents_hint_company'))"></p>

                <template x-if="!allowsIndividual || applicant === 'company'">
                    <div class="space-y-4">
                        @foreach ([
                            'doc_brela' => ['label' => $docTypes['brela'], 'required' => true],
                            'doc_tin_certificate' => ['label' => $docTypes['tin_certificate'], 'required' => true],
                            'doc_business_licence' => ['label' => $docTypes['business_licence'], 'required' => false],
                        ] as $input => $meta)
                            <div class="rounded-2xl ring-1 ring-brand/10 bg-white p-4">
                                <p class="text-sm font-semibold text-gray-900 mb-3">
                                    {{ $meta['label'] }}
                                    @if ($meta['required']) <span class="text-red-500">*</span> @endif
                                </p>
                                <x-site.single-image-document-upload :name="$input" :required="$meta['required']" />
                            </div>
                        @endforeach
                    </div>
                </template>

                <div class="space-y-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.registrant_id') }}</p>
                    @foreach ([
                        'doc_national_id_front' => $docTypes['national_id_front'],
                        'doc_national_id_back' => $docTypes['national_id_back'],
                    ] as $input => $label)
                        <div class="rounded-2xl ring-1 ring-brand/10 bg-white p-4">
                            <p class="text-sm font-semibold text-gray-900 mb-3">{{ $label }} <span class="text-red-500">*</span></p>
                            <x-site.single-image-document-upload :name="$input" :required="true" />
                        </div>
                    @endforeach
                </div>

                <template x-if="!allowsIndividual || applicant === 'company'">
                    <div class="rounded-2xl ring-1 ring-brand/10 bg-white p-4">
                        <p class="text-sm font-semibold text-gray-900 mb-3">{{ $docTypes['other'] }}</p>
                        <x-site.single-image-document-upload name="doc_other" :required="false" />
                    </div>
                </template>

                <div class="flex flex-col gap-3 pt-2">
                    <div class="flex justify-between">
                        <button type="button" @click="step = 2" class="text-sm font-semibold text-gray-600 hover:text-brand">← {{ __('site.partner_apply.back') }}</button>
                        <button type="button" @click="step = 4" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.partner_apply.next') }} →</button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Review & authorisation --}}
            <div x-show="step === 4" x-cloak class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('site.partner_apply.review_body') }}</p>
                <label class="flex items-start gap-3 rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3.5 py-3 text-sm text-gray-800 cursor-pointer">
                    <input type="checkbox" name="collection_conduct_accepted" value="1" required
                           class="mt-1 size-4 rounded border-gray-300 text-brand focus:ring-brand"
                           @checked(old('collection_conduct_accepted'))>
                    <span>
                        <span class="font-semibold text-gray-900">{{ __('site.partner_apply.conduct_title') }}</span>
                        <span class="block mt-1 text-xs text-gray-600 leading-relaxed">{{ __('site.partner_apply.conduct_body') }}</span>
                    </span>
                </label>
                <div class="flex justify-between">
                    <button type="button" @click="step = 3" class="text-sm font-semibold text-gray-600 hover:text-brand">← {{ __('site.partner_apply.back') }}</button>
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm">{{ __('site.partner_apply.submit') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-site.layout>

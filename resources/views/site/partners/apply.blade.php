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
            step: {{ $errors->hasAny(['doc_brela','doc_tin_certificate','doc_business_licence','documents','registration_number','tin']) ? 3 : ($errors->hasAny(['region','coverage_regions']) ? 2 : 1) }},
            category: @js(old('partner_category', $category)),
            applicant: @js(old('applicant_category', 'company')),
            changingType: false,
            labels: @js(collect($categories)->mapWithKeys(fn ($label, $key) => [$key => __('site.partner_apply.types.'.$key)])->all()),
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
                    <label class="cursor-pointer">
                        <input type="radio" name="partner_category_picker" value="{{ $value }}" class="peer sr-only" x-model="category">
                        <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-2.5 text-center text-sm font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50 transition">{{ __('site.partner_apply.types.'.$value) }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('site.partners.apply.post') }}" enctype="multipart/form-data" class="glass-card p-6 sm:p-8 space-y-5"
              @submit="if (!document.querySelector('input[name=partner_category]')) { $el.insertAdjacentHTML('afterbegin', `<input type=hidden name=partner_category value='${category}'>`) }">
            @csrf
            <input type="hidden" name="partner_category" :value="category">

            {{-- Tabs --}}
            <div class="flex gap-1 rounded-xl bg-gray-50 ring-1 ring-gray-200 p-1 text-sm">
                @foreach ([1 => __('site.partner_apply.tab_contact'), 2 => __('site.partner_apply.tab_location'), 3 => __('site.partner_apply.tab_documents')] as $n => $label)
                    <button type="button" @click="step = {{ $n }}"
                            class="flex-1 rounded-lg py-2.5 font-semibold transition"
                            :class="step === {{ $n }} ? 'bg-brand text-white shadow-sm' : 'text-gray-600 hover:bg-white'">
                        {{ $n }}. {{ $label }}
                    </button>
                @endforeach
            </div>

            {{-- Step 1: Contact --}}
            <div x-show="step === 1" x-cloak class="space-y-5">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('site.partner_apply.applicant_type') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['individual' => __('site.affiliate.type_individual'), 'company' => __('site.affiliate.type_company')] as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="applicant_category" value="{{ $value }}" class="peer sr-only" x-model="applicant" @checked(old('applicant_category', 'company') === $value) required>
                                <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-3 text-center text-sm font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50 transition">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.contact_name') }}</label>
                    <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                        <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone')" :required="true"
                            select-class="w-28 shrink-0 rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand"
                            input-class="flex-1 rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand" />
                    </div>
                </div>

                <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/10 p-4 space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-brand">{{ __('site.partner_apply.business_section') }}</p>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.trading_name') }}</label>
                        <input name="business_name" value="{{ old('business_name') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.legal_name') }}</label>
                        <input name="legal_name" value="{{ old('legal_name') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.legal_name_hint') }}">
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
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
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.region') }}</label>
                    <select name="region" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('site.affiliate_apply.select_region') }}</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </div>
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

            {{-- Step 3: Business documents --}}
            <div x-show="step === 3" x-cloak class="space-y-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-700">{{ __('site.partner_apply.documents_section') }}</p>
                <p class="text-xs text-gray-500">{{ __('site.partner_apply.documents_hint') }}</p>

                <div class="space-y-3">
                    @foreach ([
                        'doc_brela' => ['label' => $docTypes['brela'], 'required' => true],
                        'doc_tin_certificate' => ['label' => $docTypes['tin_certificate'], 'required' => true],
                        'doc_business_licence' => ['label' => $docTypes['business_licence'], 'required' => $category === 'debt_collector'],
                        'doc_other' => ['label' => $docTypes['other'], 'required' => false],
                    ] as $input => $meta)
                        <label class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 rounded-xl ring-1 ring-gray-200 bg-gray-50/80 px-4 py-3 cursor-pointer hover:bg-brand-muted/30 transition">
                            <span class="text-sm font-medium text-gray-800 min-w-[12rem]">
                                {{ $meta['label'] }}
                                @if ($meta['required']) <span class="text-red-500">*</span> @endif
                            </span>
                            <input type="file" name="{{ $input }}" accept=".jpg,.jpeg,.png,.pdf"
                                   class="flex-1 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-light">
                        </label>
                    @endforeach
                </div>

                <div class="flex justify-between pt-2">
                    <button type="button" @click="step = 2" class="text-sm font-semibold text-gray-600 hover:text-brand">← {{ __('site.partner_apply.back') }}</button>
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm">{{ __('site.partner_apply.submit') }}</button>
                </div>
            </div>
        </form>
    </div>
</x-site.layout>

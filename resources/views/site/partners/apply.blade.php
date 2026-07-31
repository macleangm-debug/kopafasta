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

    <div class="max-w-2xl mx-auto py-10 px-4 -mt-6">
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('site.partners.apply.post') }}" enctype="multipart/form-data" class="glass-card p-6 sm:p-8 space-y-5"
              x-data="{ category: @js(old('partner_category', $category)), applicant: @js(old('applicant_category', 'company')) }">
            @csrf

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('site.partner_apply.partner_type') }}</label>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach ($categories as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" name="partner_category" value="{{ $value }}" class="peer sr-only" x-model="category" @checked(old('partner_category', $category) === $value) required>
                            <span class="block rounded-xl ring-1 ring-gray-200 px-3 py-3 text-center text-sm font-semibold peer-checked:ring-brand peer-checked:bg-brand-muted/50 transition">{{ __('site.partner_apply.types.'.$value) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-2">{{ __('site.partner_apply.applicant_type') }}</label>
                <div class="grid sm:grid-cols-3 gap-2">
                    @foreach (['individual' => __('site.affiliate.type_individual'), 'company' => __('site.affiliate.type_company'), 'institution' => __('site.affiliate.type_institution')] as $value => $label)
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

            <div class="grid sm:grid-cols-2 gap-4">
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
                    <select name="coverage_regions[]" multiple class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm min-h-[2.75rem]">
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected(in_array($region, old('coverage_regions', []), true))>{{ $region }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-gray-500">{{ __('site.partner_apply.coverage_hint') }}</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_apply.message') }}</label>
                <textarea name="message" rows="3" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.partner_apply.message_placeholder') }}">{{ old('message') }}</textarea>
            </div>

            <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-700">{{ __('site.partner_apply.documents_section') }}</p>
                <p class="text-xs text-gray-500">{{ __('site.partner_apply.documents_hint') }}</p>
                @foreach ([
                    'doc_brela' => $docTypes['brela'],
                    'doc_tin_certificate' => $docTypes['tin_certificate'],
                    'doc_business_licence' => $docTypes['business_licence'],
                    'doc_other' => $docTypes['other'],
                ] as $input => $label)
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            {{ $label }}
                            @if (in_array($input, ['doc_brela', 'doc_tin_certificate'], true) || ($input === 'doc_business_licence' && $category === 'debt_collector'))
                                <span class="text-red-500">*</span>
                            @endif
                        </label>
                        <input type="file" name="{{ $input }}" accept=".jpg,.jpeg,.png,.pdf" class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand file:px-3 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-brand-light">
                    </div>
                @endforeach
            </div>

            <button class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-semibold px-8 py-3 rounded-xl text-sm">{{ __('site.partner_apply.submit') }}</button>
        </form>
    </div>
</x-site.layout>

@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.affiliate.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
    ];
    $payout = $vendor->metadata['payout_account'] ?? [];
    $canChangeCode = $canChangeCode ?? app(\App\Services\AffiliateService::class)->canChangeCode($vendor);
@endphp

<x-site.affiliate-layout :title="brand_title(__('site.affiliate_portal.profile_title'))" active="profile">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.title')"
        :title="__('site.affiliate_portal.profile_title')"
        :subtitle="__('site.affiliate_portal.profile_subtitle')"
    />

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @include('site.affiliate._kyc_overview', ['vendor' => $vendor])

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    <div class="space-y-6">
        <x-site.profile-section-card
            section-id="section-contact"
            :title="__('site.affiliate_portal.contact_details')"
            :complete="filled($vendor->name) && filled($vendor->phone)"
            :collapsible="true">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.affiliate_apply.full_name') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $vendor->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.affiliate_apply.email') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $vendor->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.affiliate_apply.phone') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $vendor->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.address') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $vendor->address ?: '—' }}</dd>
                    </div>
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.affiliate.profile.update') }}" class="grid sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.full_name') }}</label>
                        <input name="name" value="{{ old('name', $vendor->name) }}" required
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                        <input type="email" name="email" value="{{ old('email', $vendor->email) }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                    </div>
                    <div>
                        <x-site.phone-input name="phone" :label="__('site.affiliate_apply.phone')" :value="old('phone', $vendor->phone)" variant="rounded" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.address') }}</label>
                        <input name="address" value="{{ old('address', $vendor->address) }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10 outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                            {{ __('site.affiliate_portal.save_profile') }}
                        </button>
                    </div>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        @php
            $residence = $vendor->metadata['residence'] ?? [];
            $activity = $vendor->metadata['activity'] ?? [];
        @endphp

        <x-site.profile-section-card
            :title="__('site.partner_account.residence_section')"
            :complete="filled($residence['region'] ?? null) && filled($residence['district'] ?? null)"
            :collapsible="true">
            <x-slot:view>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.region') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['region'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.district') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['district'] ?? '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500">{{ __('site.partner_account.street') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['street'] ?? '—' }}</dd>
                    </div>
                </dl>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.affiliate.profile.update') }}" class="grid sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $vendor->name }}">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_account.region') }}</label>
                        <input name="residence_region" value="{{ old('residence_region', $residence['region'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_account.district') }}</label>
                        <input name="residence_district" value="{{ old('residence_district', $residence['district'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_account.street') }}</label>
                        <input name="residence_street" value="{{ old('residence_street', $residence['street'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.save_profile') }}</button>
                    </div>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        <x-site.profile-section-card
            :title="__('site.partner_account.activity_section')"
            :complete="filled($activity['type'] ?? null)"
            :collapsible="true">
            <x-slot:view>
                <p class="text-sm font-semibold text-gray-900">{{ $activity['type'] ?? '—' }}</p>
                <p class="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{{ $activity['details'] ?? '—' }}</p>
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.affiliate.profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $vendor->name }}">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_account.activity_type') }}</label>
                        <input name="activity_type" value="{{ old('activity_type', $activity['type'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.partner_account.activity_details') }}</label>
                        <textarea name="activity_details" rows="3" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">{{ old('activity_details', $activity['details'] ?? '') }}</textarea>
                    </div>
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">{{ __('site.affiliate_portal.save_profile') }}</button>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        <x-site.profile-section-card
            :title="__('site.affiliate_portal.personalize_code')"
            :complete="filled($vendor->affiliate_code)"
            :collapsible="true">
            <x-slot:view>
                <p class="text-sm font-mono font-bold text-brand">{{ $vendor->affiliate_code ?: '—' }}</p>
                <p class="text-xs text-gray-500 mt-2">
                    {{ $canChangeCode ? __('site.affiliate_portal.code_change_hint') : __('site.affiliate_portal.code_locked_hint') }}
                </p>
                @if ($vendor->affiliate_code)
                    @php $verifyUrl = $links['verify_link'] ?? route('site.affiliate.verify', $vendor->affiliate_code); @endphp
                    <a href="{{ $verifyUrl }}" target="_blank" class="inline-flex mt-3 text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.verify_link') }} →</a>
                @endif
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.affiliate.profile.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $vendor->name }}">
                    <p class="text-sm text-gray-600">{{ __('site.affiliate_portal.code_rules') }}</p>
                    <p class="text-xs text-amber-700">{{ $canChangeCode ? __('site.affiliate_portal.code_change_hint') : __('site.affiliate_portal.code_locked_hint') }}</p>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_portal.promo_code') }}</label>
                        <input name="affiliate_code" value="{{ old('affiliate_code', $vendor->affiliate_code) }}"
                               pattern="[A-Za-z0-9_-]{3,24}" maxlength="24"
                               @disabled(! $canChangeCode)
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm font-mono uppercase focus:border-brand focus:ring-brand/10 outline-none disabled:bg-gray-50 disabled:text-gray-500">
                        @error('affiliate_code')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    @if ($canChangeCode)
                        <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                            {{ __('site.affiliate_portal.save_profile') }}
                        </button>
                    @endif
                </form>
            </x-slot:form>
        </x-site.profile-section-card>

        <x-site.profile-section-card
            :title="__('borrower.payment_details.section_title')"
            :complete="! empty($payout)"
            :collapsible="true">
            <x-slot:view>
                @if (empty($payout))
                    <p class="text-sm text-gray-600">{{ __('site.partner_account.payment_empty') }}</p>
                @else
                    <p class="text-sm font-semibold text-gray-900 capitalize">{{ str_replace('_', ' ', $payout['type'] ?? '') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ $payout['account_name'] ?? '' }}</p>
                    <p class="text-sm font-mono text-gray-800 mt-1">{{ $payout['mobile_number'] ?? $payout['account_number'] ?? '' }}</p>
                @endif
            </x-slot:view>
            <x-slot:form>
                <form method="POST" action="{{ route('site.affiliate.profile.update') }}" class="space-y-4" x-data="{ type: @js(old('payout_type', $payout['type'] ?? 'mobile_money')) }">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="name" value="{{ $vendor->name }}">
                    <p class="text-sm text-gray-600">{{ __('site.partner_account.payment_hint') }}</p>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="payout_type" value="mobile_money" x-model="type" class="text-brand">
                            {{ __('borrower.payment_details.method_mobile') }}
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="payout_type" value="bank" x-model="type" class="text-brand">
                            {{ __('borrower.payment_details.method_bank') }}
                        </label>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.account_name') }}</label>
                        <input name="payout_account_name" value="{{ old('payout_account_name', $payout['account_name'] ?? $vendor->name) }}"
                               class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                    </div>
                    <div x-show="type === 'mobile_money'" class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.provider') }}</label>
                            <input name="payout_mobile_provider" value="{{ old('payout_mobile_provider', $payout['mobile_provider'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="M-Pesa / Tigo Pesa / Airtel Money">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.phone_number') }}</label>
                            <input name="payout_mobile_number" value="{{ old('payout_mobile_number', $payout['mobile_number'] ?? $vendor->phone) }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                    <div x-show="type === 'bank'" class="grid sm:grid-cols-2 gap-4" x-cloak>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.bank_name') }}</label>
                            <input name="payout_bank_name" value="{{ old('payout_bank_name', $payout['bank_name'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.account_number') }}</label>
                            <input name="payout_account_number" value="{{ old('payout_account_number', $payout['account_number'] ?? '') }}"
                                   class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        </div>
                    </div>
                    <button type="submit" class="bg-brand hover:bg-brand-light text-white font-semibold px-6 py-2.5 rounded-xl text-sm">
                        {{ __('site.affiliate_portal.save_profile') }}
                    </button>
                </form>
            </x-slot:form>
        </x-site.profile-section-card>
    </div>

</x-site.affiliate-layout>

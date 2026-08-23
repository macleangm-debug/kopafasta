@props([
    'partner',
    'portal',
    'profileRoute',
    'updateRoute',
    'layoutComponent',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'accountTabs' => [],
])

@php
    $meta = $partner->metadata ?? [];
    $payout = is_array($meta['payout_account'] ?? null) ? $meta['payout_account'] : [];
    $lockedName = app(\App\Services\PartnerProfileService::class)->payoutAccountName($partner);
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.borrower-page-header :eyebrow="$eyebrow" :title="$title" :subtitle="$subtitle" share="kf-psec-payment" />

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'payment',
        'profileRoute' => $profileRoute,
    ])

    <x-site.profile-section-card
        section-id="section-payment"
        icon="💳"
        :title="__('site.partner_account.payment_section')"
        :complete="! empty($payout)"
        :collapsible="true"
        :default-open="true"
        :default-edit="true">
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
            <form method="POST" action="{{ route($updateRoute, ['section' => 'payment']) }}" class="space-y-4" x-data="{ type: @js(old('payout_type', $payout['type'] ?? 'mobile_money')) }">
                @csrf @method('PUT')
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
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3">
                    <p class="text-xs text-gray-500">{{ __('borrower.payment_details.account_name') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $lockedName }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ __('site.partner_account.payout_name_locked') }}</p>
                    <input type="hidden" name="payout_account_name" value="{{ $lockedName }}">
                </div>
                <div x-show="type === 'mobile_money'" class="grid sm:grid-cols-2 gap-4 items-start">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.provider') }}</label>
                        <input name="payout_mobile_provider" value="{{ old('payout_mobile_provider', $payout['mobile_provider'] ?? '') }}"
                               class="w-full h-12 rounded-xl bg-white border border-gray-300 px-3.5 text-base outline-none transition focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10" placeholder="M-Pesa / Tigo Pesa / Airtel Money">
                    </div>
                    <div class="min-w-0">
                        <x-site.phone-input
                            name="payout_mobile_number"
                            :label="__('borrower.payment_details.phone_number')"
                            :value="old('payout_mobile_number', $payout['mobile_number'] ?? $partner->phone)"
                            variant="rounded"
                            prefix-class="h-12 py-0"
                        />
                    </div>
                </div>
                <div x-show="type === 'bank'" class="grid sm:grid-cols-2 gap-4 items-start" x-cloak>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.bank_name') }}</label>
                        <input name="payout_bank_name" value="{{ old('payout_bank_name', $payout['bank_name'] ?? '') }}"
                               class="w-full h-12 rounded-xl bg-white border border-gray-300 px-3.5 text-base outline-none transition focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('borrower.payment_details.account_number') }}</label>
                        <input name="payout_account_number" value="{{ old('payout_account_number', $payout['account_number'] ?? '') }}"
                               class="w-full h-12 rounded-xl bg-white border border-gray-300 px-3.5 text-base outline-none transition focus:border-gray-900 focus:ring-4 focus:ring-gray-900/10">
                    </div>
                </div>
                <button type="submit" class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5">
                    {{ __('site.partner_account.save_payment') }}
                </button>
            </form>
        </x-slot:form>
    </x-site.profile-section-card>

</x-dynamic-component>

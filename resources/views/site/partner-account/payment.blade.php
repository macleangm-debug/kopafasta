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
@endphp

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.borrower-page-header :eyebrow="$eyebrow" :title="$title" :subtitle="$subtitle" />

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
        :default-open="true">
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
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('borrower.payment_details.account_name') }}</label>
                    <input name="payout_account_name" value="{{ old('payout_account_name', $payout['account_name'] ?? $partner->name) }}"
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
                        <input name="payout_mobile_number" value="{{ old('payout_mobile_number', $payout['mobile_number'] ?? $partner->phone) }}"
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
                <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
            </form>
        </x-slot:form>
    </x-site.profile-section-card>

</x-dynamic-component>

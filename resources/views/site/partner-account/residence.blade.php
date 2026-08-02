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
    $residence = is_array($meta['residence'] ?? null) ? $meta['residence'] : [];
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
        'active' => 'residence',
        'profileRoute' => $profileRoute,
    ])

    <x-site.profile-section-card
        section-id="section-residence"
        icon="🏠"
        :title="__('site.partner_account.residence_section')"
        :complete="filled($residence['region'] ?? null) && filled($residence['district'] ?? null)"
        :collapsible="true"
        :default-open="true">
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
            <form method="POST" action="{{ route($updateRoute, ['section' => 'residence']) }}" class="grid sm:grid-cols-2 gap-4">
                @csrf @method('PUT')
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.region') }}</label>
                    <input name="residence_region" value="{{ old('residence_region', $residence['region'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.district') }}</label>
                    <input name="residence_district" value="{{ old('residence_district', $residence['district'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-brand mb-1">{{ __('site.partner_account.street') }}</label>
                    <input name="residence_street" value="{{ old('residence_street', $residence['street'] ?? '') }}" class="w-full rounded-xl border-gray-200 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div class="sm:col-span-2">
                    <button type="submit" class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5">{{ __('site.partner_account.save_profile') }}</button>
                </div>
            </form>
        </x-slot:form>
    </x-site.profile-section-card>

</x-dynamic-component>

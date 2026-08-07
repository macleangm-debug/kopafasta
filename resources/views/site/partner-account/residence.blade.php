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
    $isCompany = $partner instanceof \App\Models\Partner && $partner->isCompanyApplicant();
    $meta = $partner->metadata ?? [];
    $residence = is_array($meta['residence'] ?? null) ? $meta['residence'] : [];
    $sectionTitle = $isCompany
        ? __('site.partner_account.company_address_section')
        : __('site.partner_account.residence_section');
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
        :title="$sectionTitle"
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
                <div>
                    <dt class="text-xs text-gray-500">{{ __('borrower.profile.fields.ward') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['ward'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.partner_account.street') }}</dt>
                    <dd class="font-semibold text-gray-900 mt-0.5">{{ $residence['street'] ?? '—' }}</dd>
                </div>
            </dl>
        </x-slot:view>
        <x-slot:form>
            <form method="POST" action="{{ route($updateRoute, ['section' => 'residence']) }}" class="space-y-4">
                @csrf @method('PUT')
                <x-site.address-fields
                    prefix="residence"
                    :region="$residence['region'] ?? ''"
                    :district="$residence['district'] ?? ''"
                    :ward="$residence['ward'] ?? ''"
                    :street="$residence['street'] ?? ''"
                    :required="true"
                />
                <x-site.gated-submit class="rounded-xl bg-brand hover:bg-brand-light text-white text-sm font-semibold px-5 py-2.5" :label="__('site.partner_account.save_profile')" />
            </form>
        </x-slot:form>
    </x-site.profile-section-card>

</x-dynamic-component>

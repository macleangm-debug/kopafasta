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

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile">

    <x-site.borrower-page-header :eyebrow="$eyebrow" :title="$title" :subtitle="$subtitle" />

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'company',
        'profileRoute' => $profileRoute,
    ])

    <div class="glass-card rounded-2xl ring-1 ring-brand/10 p-5 sm:p-6 space-y-4">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('site.partner_account.company_section') }}</p>
            <p class="text-sm text-gray-600 mt-1">{{ __('site.partner_account.business_meta_hint') }}</p>
        </div>
        <dl class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="text-xs text-gray-500">{{ __('site.partner_account.display_name') }}</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $partner->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ __('site.partner_account.legal_name') }}</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ $partner->legal_name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ __('site.partner_account.registration') }}</dt>
                <dd class="font-mono font-semibold text-gray-900 mt-0.5">{{ $partner->registration_number ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">{{ __('site.partner_account.tin') }}</dt>
                <dd class="font-mono font-semibold text-gray-900 mt-0.5">{{ $partner->tin ?: '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs text-gray-500">{{ __('site.partner_account.category') }}</dt>
                <dd class="font-semibold text-gray-900 mt-0.5">{{ ucfirst(str_replace('_', ' ', (string) $partner->category)) }}</dd>
            </div>
        </dl>
    </div>

</x-dynamic-component>

@php
    $showShell = isset($partner, $profileRoute);
@endphp

<x-site.affiliate-layout
    :title="brand_title($title ?? __('site.affiliate_portal.agreement_title'))"
    active="profile"
    :hero="[
        'title' => ($commercial['premium'] ?? false)
            ? __('site.affiliate_portal.premium_agreement')
            : __('site.affiliate_portal.agreement_terms_section'),
        'body' => __('site.affiliate_portal.agreement_subtitle'),
    ]"
>

    @if (! empty($accountTabs))
        <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />
    @endif

    @if ($showShell)
        @include('site.partner-account._tabs', [
            'active' => 'agreement',
            'partner' => $partner,
            'profileRoute' => $profileRoute,
            'portal' => $portal ?? 'affiliate',
        ])
    @endif

    @php
        $accepted = (bool) $acceptance;
        $agreementName = ($commercial['premium'] ?? false)
            ? __('site.affiliate_portal.premium_agreement')
            : __('affiliate_terms.title');
        $expiresAt = $commercial['expires_at'] ?? null;
    @endphp

    <div class="glass-card p-5 mb-5 space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-xs uppercase tracking-widest font-bold text-gray-500">{{ __('site.affiliate_portal.agreement_terms_section') }}</p>
                <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $agreementName }}</h2>
            </div>
            <span @class([
                'inline-flex items-center rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wide',
                $accepted ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
            ])>
                {{ $accepted ? __('site.affiliate_portal.agreement_status_accepted') : __('site.affiliate_portal.agreement_status_pending') }}
            </span>
        </div>
        <dl class="grid sm:grid-cols-2 gap-3 text-sm">
            @if ($accepted)
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.accepted_date_label') }}</dt>
                    <dd class="font-semibold text-gray-900">{{ $acceptance->accepted_at?->format('d M Y') ?: '—' }}</dd>
                </div>
            @endif
            @if ($acceptance?->agreement_version)
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.agreement_version_label') }}</dt>
                    <dd class="font-semibold text-gray-900">v{{ $acceptance->agreement_version }}@if ($acceptance->policy_version) / policy v{{ $acceptance->policy_version }}@endif</dd>
                </div>
            @endif
            @if (! empty($commercial['duration_months']))
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.agreement_duration_label') }}</dt>
                    <dd class="font-semibold text-gray-900">{{ __('site.affiliate_portal.agreement_duration_months', ['months' => $commercial['duration_months']]) }}</dd>
                </div>
            @endif
            @if ($expiresAt)
                <div>
                    <dt class="text-xs text-gray-500">{{ __('site.affiliate_portal.agreement_expires_label') }}</dt>
                    <dd class="font-semibold text-gray-900">{{ $expiresAt->format('d M Y') }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <x-site.branded-agreement :header="$header" :sections="$sections" />

</x-site.affiliate-layout>

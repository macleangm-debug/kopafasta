@php
    $showShell = isset($partner, $profileRoute);
@endphp

<x-site.affiliate-layout :title="brand_title($title ?? __('site.affiliate_portal.agreement_title'))" active="profile">

    <x-site.borrower-page-header
        :eyebrow="$eyebrow ?? __('site.affiliate_portal.agreement_title')"
        :title="$commercial['premium'] ?? false ? __('site.affiliate_portal.premium_agreement') : __('site.affiliate_portal.membership_title')"
        :subtitle="__('site.affiliate_portal.agreement_subtitle')"
    />

    @if (! empty($accountTabs))
        <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />
    @endif

    @if ($showShell)
        @include('site.partner-account._shell', [
            'partner' => $partner,
            'portal' => $portal ?? 'affiliate',
            'active' => ($commercial['premium'] ?? false) ? 'agreement' : 'membership',
            'profileRoute' => $profileRoute,
        ])
    @endif

    <x-site.branded-agreement :header="$header" :sections="$sections">
        <div class="flex flex-wrap gap-3 pt-2">
            <a href="{{ route('site.affiliate.terms') }}" class="inline-flex text-sm font-semibold text-brand hover:underline">{{ __('site.affiliate_portal.view_terms') }} →</a>
            @if ($acceptance)
                <span class="text-sm text-emerald-700">{{ __('site.affiliate_portal.accepted_on', ['date' => $acceptance->accepted_at?->format('d M Y')]) }}</span>
            @endif
        </div>
    </x-site.branded-agreement>

</x-site.affiliate-layout>

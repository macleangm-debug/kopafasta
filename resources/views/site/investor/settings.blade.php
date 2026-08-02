@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.investor.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.investor.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.investor.settings')],
    ];
@endphp

<x-site.investor-layout title="Settings — Capital partner" active="profile">
    <x-site.borrower-page-header
        eyebrow="Capital partner"
        title="Settings"
        subtitle="Security and account preferences for your capital portal."
    />

    <x-site.partner-account-tabs active="settings" :tabs="$accountTabs" />

    @include('site.partner-account._settings', [
        'partner' => (object) [
            'name' => $lender->name,
            'partner_number' => $lender->code,
            'email' => $lender->email,
        ],
        'supportRoute' => route('site.investor.support'),
        'pinUpdateRoute' => route('site.investor.settings.pin'),
    ])
</x-site.investor-layout>

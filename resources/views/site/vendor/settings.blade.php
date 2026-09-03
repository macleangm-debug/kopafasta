@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.partner.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.partner.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.partner.settings')],
    ];
@endphp

<x-site.vendor-layout :title="__('site.partner_account.settings_title')" active="profile">
    <x-site.borrower-page-header
        :eyebrow="ucfirst(str_replace('_', ' ', $vendor->category ?? 'partner'))"
        :title="__('site.partner_account.settings_title')"
        :subtitle="__('site.partner_account.settings_subtitle')"
    />

    <x-site.partner-account-tabs active="settings" :tabs="$accountTabs" />

    @include('site.partner-account._settings', [
        'partner' => $vendor,
        'supportRoute' => route('site.partner.support'),
        'pinUpdateRoute' => route('site.partner.settings.pin'),
        'preferencesUpdateRoute' => route('site.partner.settings.preferences'),
    ])
</x-site.vendor-layout>

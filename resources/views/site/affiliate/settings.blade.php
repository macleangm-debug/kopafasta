@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.affiliate.profile')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.affiliate.settings')],
    ];
@endphp

<x-site.affiliate-layout :title="brand_title(__('site.partner_account.settings_title'))" active="profile">

    <x-site.borrower-page-header
        :eyebrow="__('site.affiliate_portal.title')"
        :title="__('site.partner_account.settings_title')"
        :subtitle="__('site.partner_account.settings_subtitle')"
    />

    <x-site.partner-account-tabs active="settings" :tabs="$accountTabs" />

    @include('site.partner-account._settings', [
        'partner' => $vendor,
        'supportRoute' => null,
        'pinUpdateRoute' => route('site.affiliate.settings.pin'),
    ])

</x-site.affiliate-layout>

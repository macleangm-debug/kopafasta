@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.partner.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.partner.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.partner.settings')],
    ];
@endphp

<x-site.vendor-layout :title="__('site.partner_account.documents_title')" active="documents">
    <x-site.partner-account-tabs active="documents" :tabs="$accountTabs" />

    @include('site.partner-account._documents', [
        'documents' => $documents,
        'uploadRoute' => route('site.partner.documents.store'),
        'documentTypes' => app(\App\Services\PartnerProfileService::class)->documentTypesFor($vendor),
    ])
</x-site.vendor-layout>

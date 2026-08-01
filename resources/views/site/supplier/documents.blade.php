@php
    $accountTabs = [
        ['key' => 'profile', 'label' => __('site.partner_account.tab_profile'), 'url' => route('site.supplier.profile')],
        ['key' => 'documents', 'label' => __('site.partner_account.tab_documents'), 'url' => route('site.supplier.documents')],
        ['key' => 'settings', 'label' => __('site.partner_account.tab_settings'), 'url' => route('site.supplier.settings')],
    ];
@endphp

<x-site.supplier-layout :title="__('site.supplier_portal.documents_title')" active="profile">
    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.title')"
        :title="__('site.supplier_portal.documents_title')"
        :subtitle="__('site.supplier_portal.documents_subtitle')"
    />

    <x-site.partner-account-tabs active="documents" :tabs="$accountTabs" />

    @include('site.partner-account._documents', [
        'documents' => $documents,
        'uploadRoute' => route('site.supplier.documents.store'),
    ])
</x-site.supplier-layout>

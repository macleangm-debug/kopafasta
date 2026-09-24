<x-site.supplier-layout :title="__('site.supplier_portal.settings_title')" active="profile">
    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.title')"
        :title="__('site.supplier_portal.settings_title')"
        :subtitle="__('site.supplier_portal.settings_subtitle')"
    />

    @include('site.partner-account._tabs', [
        'active' => 'settings',
        'partner' => $vendor,
        'profileRoute' => 'site.supplier.profile',
        'portal' => 'supplier',
    ])

    @include('site.partner-account._settings', [
        'partner' => $vendor,
        'supportRoute' => null,
        'pinUpdateRoute' => route('site.supplier.settings.pin'),
        'preferencesUpdateRoute' => route('site.supplier.settings.preferences'),
    ])
</x-site.supplier-layout>

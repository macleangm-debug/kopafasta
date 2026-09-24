<x-site.supplier-layout :title="__('site.supplier_portal.settings_title')" active="profile" hero-key="settings">
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

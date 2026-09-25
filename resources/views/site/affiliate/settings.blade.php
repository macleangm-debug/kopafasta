<x-site.affiliate-layout :title="brand_title(__('site.partner_account.settings_title'))" active="profile" hero-key="settings">

    @include('site.partner-account._settings', [
        'partner' => $vendor,
        'supportRoute' => null,
        'pinUpdateRoute' => route('site.affiliate.settings.pin'),
        'preferencesUpdateRoute' => route('site.affiliate.settings.preferences'),
    ])

</x-site.affiliate-layout>

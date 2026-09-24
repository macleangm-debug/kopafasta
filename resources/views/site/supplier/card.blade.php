<x-site.supplier-layout :title="__('site.supplier_portal.card_title')" active="profile" content-width="wide" hero-key="card">
    @include('site.partner-account._tabs', [
        'active' => 'card',
        'partner' => $partner,
        'profileRoute' => $profileRoute,
        'portal' => $portal,
    ])

    @include('site.partner-account._member_card', ['partner' => $partner])
</x-site.supplier-layout>

<x-site.supplier-layout :title="__('site.supplier_portal.card_title')" active="profile" content-width="wide">
    <x-site.borrower-page-header
        :eyebrow="__('site.supplier_portal.title')"
        :title="__('site.supplier_portal.card_title')"
        :subtitle="__('site.supplier_portal.card_subtitle')"
    />

    @include('site.partner-account._tabs', [
        'active' => 'card',
        'partner' => $partner,
        'profileRoute' => $profileRoute,
        'portal' => $portal,
    ])

    @include('site.partner-account._member_card', ['partner' => $partner])
</x-site.supplier-layout>

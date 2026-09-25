<x-site.supplier-layout :title="__('site.card_verify.my_card_title')" active="profile" content-width="wide" :hero="false">
    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'card',
        'profileRoute' => $profileRoute,
    ])
</x-site.supplier-layout>

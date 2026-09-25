@props([
    'partner',
    'portal',
    'profileRoute',
    'layoutComponent',
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'accountTabs' => [],
])

<x-dynamic-component :component="$layoutComponent" :title="brand_title($title)" active="profile" content-width="wide" :hero="false">

    <x-site.partner-account-tabs active="profile" :tabs="$accountTabs" />

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'card',
        'profileRoute' => $profileRoute,
    ])

</x-dynamic-component>

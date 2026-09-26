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

    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
    @endif

    @include('site.partner-account._shell', [
        'partner' => $partner,
        'portal' => $portal,
        'active' => 'hub',
        'profileRoute' => $profileRoute,
    ])

</x-dynamic-component>

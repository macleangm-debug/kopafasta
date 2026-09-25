@props([
    'partner',
    'portal',
    'active' => 'hub',
    'profileRoute',
])

@php
    $hubUrl = route($profileRoute);
@endphp

@if ($active === 'membership')
    <x-site.account-shell-hero
        mode="contextual"
        :title="__('site.card_verify.my_card_title')"
        :cta-url="$hubUrl"
        :cta-label="__('site.partner_portal.nav_profile')"
    />
@endif

@if ($active === 'hub')
    @include('site.partner-account._member_card', ['partner' => $partner])
    @include('site.partner-account._overview', ['partner' => $partner, 'profileRoute' => $profileRoute, 'portal' => $portal])
@elseif ($active === 'membership')
    {{-- Membership commercial pages render their own body; hero above is contextual. --}}
@else
    @include('site.partner-account._tabs', ['active' => $active, 'partner' => $partner, 'profileRoute' => $profileRoute, 'portal' => $portal])
@endif

@props([
    'partner',
    'portal',
    'active' => 'hub',
    'profileRoute',
])

@if ($active !== 'hub')
    <div class="mb-4">
        <a href="{{ route($profileRoute) }}" data-kf-motion="pop" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
            ← {{ __('site.partner_account.hub_back') }}
        </a>
    </div>
@endif

@if ($active === 'hub')
    @include('site.partner-account._member_card', ['partner' => $partner])
    @include('site.partner-account._overview', ['partner' => $partner, 'profileRoute' => $profileRoute])
@else
    @include('site.partner-account._tabs', ['active' => $active, 'partner' => $partner, 'profileRoute' => $profileRoute, 'portal' => $portal])
@endif

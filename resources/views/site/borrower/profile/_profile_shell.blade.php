@props([
    'title',
    'subtitle',
    'customer',
    'active' => 'personal',
    'accountPanel' => 'profile',
    'wizardMode' => false,
    'wizardKey' => null,
])

@if (request()->boolean('solo'))
    @php $soloAppId = (int) request('application'); @endphp
    @if ($soloAppId > 0)
        <div class="mb-4">
            <a href="{{ route('site.borrower.application', $soloAppId) }}" data-kf-motion="pop" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
                ← {{ __('borrower.notifications.view_application') }}
            </a>
        </div>
    @endif
@endif

@include('site.borrower.profile._heading', [
    'title' => ($active ?? '') === 'hub' ? $title : null,
    'subtitle' => ($active ?? '') === 'hub' ? $subtitle : null,
    'share' => ($active ?? '') !== 'hub' ? 'kf-prof-'.$active : null,
])

@if (! $wizardMode && ! request()->boolean('solo'))
    @include('site.borrower.profile._member_card', [
        'customer' => $customer,
        'cta' => ($accountPanel ?? 'profile') === 'membership' ? 'profile' : 'card',
    ])
@endif

@if ($wizardMode)
    @include('site.borrower.profile._kyc_progress', [
        'customer' => $customer,
        'active' => $active,
        'wizardMode' => true,
        'wizardKey' => $wizardKey,
    ])
@elseif (($accountPanel ?? 'profile') === 'profile' && ($active ?? '') === 'hub')
    @include('site.borrower.profile._profile_overview', ['customer' => $customer])
@elseif (($accountPanel ?? 'profile') === 'profile' && ($active ?? '') !== 'hub' && ! request()->boolean('solo'))
    @include('site.borrower.profile._tabs', ['active' => $active ?? 'personal', 'customer' => $customer])
@endif

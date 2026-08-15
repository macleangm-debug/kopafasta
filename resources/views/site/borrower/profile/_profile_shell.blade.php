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
@elseif (! $wizardMode && ($active ?? '') !== 'hub')
    <div class="mb-4">
        <a href="{{ route('site.borrower.profile') }}" data-kf-motion="pop" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.profile.hub.back') }}
        </a>
    </div>
@endif

@include('site.borrower.profile._heading', [
    'title' => $title,
    'subtitle' => ($active ?? '') === 'hub' ? $subtitle : null,
    'share' => ($active ?? '') !== 'hub' ? 'kf-prof-'.$active : null,
])

@if (! $wizardMode && ! request()->boolean('solo'))
    @include('site.borrower.profile._account_segments', ['activePanel' => $accountPanel])
    @if ($accountPanel === 'profile' && ($active ?? '') === 'hub')
        @include('site.borrower.profile._member_card', ['customer' => $customer])
    @endif
@endif

@if ($wizardMode)
    @include('site.borrower.profile._kyc_progress', [
        'customer' => $customer,
        'active' => $active,
        'wizardMode' => true,
        'wizardKey' => $wizardKey,
    ])
@elseif ($accountPanel === 'profile' && ($active ?? '') === 'hub')
    @include('site.borrower.profile._profile_overview', ['customer' => $customer])
@elseif ($accountPanel === 'profile' && ($active ?? '') !== 'hub' && ! request()->boolean('solo'))
    @include('site.borrower.profile._tabs', ['active' => $active ?? 'personal', 'customer' => $customer])
@endif

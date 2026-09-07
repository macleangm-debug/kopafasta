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
@elseif (! $wizardMode && ($active ?? '') !== 'hub' && ($accountPanel ?? 'profile') !== 'membership')
    <div class="mb-4">
        <a href="{{ route('site.borrower.profile') }}" data-kf-motion="pop" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.profile.hub.back') }}
        </a>
    </div>
@endif

@include('site.borrower.profile._heading', [
    'title' => ($active ?? '') === 'hub' ? $title : null,
    'subtitle' => ($active ?? '') === 'hub' ? $subtitle : null,
    'share' => ($active ?? '') !== 'hub' ? 'kf-prof-'.$active : null,
])

@if (! $wizardMode && ! request()->boolean('solo'))
    @if (($accountPanel ?? 'profile') === 'membership')
        {{-- My Card: Profile CTA only — no duplicate mini identity card --}}
        <div class="mb-5 flex justify-end">
            <a href="{{ route('site.borrower.profile') }}"
               data-kf-motion="pop"
               class="inline-flex items-center justify-center rounded-full bg-brand text-white hover:bg-brand-light font-bold px-4 py-2.5 text-sm shadow-sm">
                {{ __('borrower.profile.panel_profile') }} →
            </a>
        </div>
    @else
        @include('site.borrower.profile._member_card', ['customer' => $customer, 'cta' => 'card'])
    @endif
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

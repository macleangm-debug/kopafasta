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

@if (! request()->boolean('solo') && session('status') && ! session('kf_suppress_saved'))
    <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 px-3.5 py-1.5 text-xs font-bold"
         role="status" data-kf-saved-toast>
        <span aria-hidden="true">✓</span>
        <span>{{ session('status') }}</span>
    </div>
@endif

{{-- Marker for shared saving-overlay: Profile never uses the blocking modal. --}}
<div data-kf-profile-page hidden aria-hidden="true"></div>

{{-- Hub uses the identity hero only — no redundant Akaunti yangu / My account heading. --}}
@include('site.borrower.profile._heading', [
    'title' => null,
    'subtitle' => null,
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

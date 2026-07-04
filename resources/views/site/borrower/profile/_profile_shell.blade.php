@props([
    'title',
    'subtitle',
    'customer',
    'active' => 'personal',
    'accountPanel' => 'profile',
    'wizardMode' => false,
    'wizardKey' => null,
])

@include('site.borrower.profile._heading', [
    'title' => $title,
    'subtitle' => $subtitle,
])

@if (! $wizardMode)
    @include('site.borrower.profile._account_segments', ['activePanel' => $accountPanel])
    @if ($accountPanel === 'profile')
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
@elseif ($accountPanel === 'profile' && ($active ?? '') !== 'hub')
    <div class="mb-6">
        <a href="{{ route('site.borrower.profile') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.profile.hub.back') }}
        </a>
    </div>
@endif

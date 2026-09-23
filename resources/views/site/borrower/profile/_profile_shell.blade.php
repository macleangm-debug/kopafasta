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

{{-- Marker for shared saving-overlay: Profile never uses the blocking modal. --}}
<div data-kf-profile-page hidden aria-hidden="true"></div>

{{-- Hub uses the identity hero only — no redundant Akaunti yangu / My account heading. --}}
@include('site.borrower.profile._heading', [
    'title' => null,
    'subtitle' => null,
    'share' => ($active ?? '') !== 'hub' ? 'kf-prof-'.$active : null,
])

@php
    $policyNotice = isset($customer) ? app(\App\Services\ProfileCompletionService::class)->policyUpdateNotice($customer) : null;
@endphp
@if (! empty($policyNotice['items']))
    <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-4 text-sm text-amber-950" role="status">
        <p class="font-semibold">{{ $policyNotice['title'] }}</p>
        <p class="mt-1 text-amber-900">{{ $policyNotice['body'] }}</p>
        <ul class="mt-3 space-y-1">
            @foreach ($policyNotice['items'] as $item)
                <li>
                    @if (! empty($item['url']))
                        <a href="{{ $item['url'] }}" class="font-semibold underline">{{ $item['label'] }}</a>
                    @else
                        <span class="font-semibold">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif

@if (! $wizardMode && ! request()->boolean('solo'))
    @include('site.borrower.profile._member_card', [
        'customer' => $customer,
        'cta' => 'card',
        'showCompletion' => true,
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

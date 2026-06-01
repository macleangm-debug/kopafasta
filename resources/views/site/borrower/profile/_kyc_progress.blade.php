@props(['customer', 'active' => 'personal'])

@php
    $profile = app(\App\Services\ProfileCompletionService::class)->calculate($customer);
    $sections = collect($profile['sections'] ?? [])->keyBy('key');
    $nidaVerified = app(\App\Services\NidaVerificationService::class)->isVerified($customer);
    $faceVerified = ($customer->face_verification_status ?? '') === 'verified';

    $steps = [
        ['key' => 'nida', 'label' => __('borrower.kyc_progress.nida'), 'complete' => $nidaVerified, 'route' => route('site.borrower.profile', ['section' => 'personal'])],
        ['key' => 'face', 'label' => __('borrower.kyc_progress.face'), 'complete' => $faceVerified, 'route' => route('site.borrower.face-verification')],
        ['key' => 'activity', 'label' => __('borrower.profile.activity'), 'complete' => (bool) ($sections['activity']['complete'] ?? false), 'route' => route('site.borrower.profile', ['section' => 'activity'])],
        ['key' => 'residence', 'label' => __('borrower.profile.residence'), 'complete' => (bool) ($sections['residence']['complete'] ?? false), 'route' => route('site.borrower.profile', ['section' => 'residence'])],
        ['key' => 'kin', 'label' => __('borrower.profile.kin'), 'complete' => (bool) ($sections['kin']['complete'] ?? false), 'route' => route('site.borrower.profile', ['section' => 'kin'])],
    ];

    $next = collect($steps)->first(fn ($step) => ! $step['complete']);
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5">
    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-3">{{ __('borrower.kyc_progress.title') }}</p>
    <ol class="flex flex-wrap items-center gap-2 text-sm">
        @foreach ($steps as $index => $step)
            @php
                $isActive = ($active === 'personal' && $step['key'] === 'nida')
                    || ($active === 'activity' && $step['key'] === 'activity')
                    || ($active === 'residence' && $step['key'] === 'residence')
                    || ($active === 'kin' && $step['key'] === 'kin')
                    || ($active === 'kyc' && $step['key'] === 'face');
            @endphp
            <li class="flex items-center gap-2">
                @if ($index > 0)
                    <span class="text-gray-300">→</span>
                @endif
                <a href="{{ $step['route'] }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 {{ $step['complete'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($isActive ? 'bg-amber-50 text-amber-900 ring-amber-200 font-semibold' : 'bg-gray-50 text-gray-700 ring-gray-200') }}">
                    <span>{{ $step['complete'] ? '✓' : '○' }}</span>
                    <span>{{ $step['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ol>
    @if ($next)
        <p class="text-xs text-gray-500 mt-3">{{ __('borrower.kyc_progress.next') }}: <a href="{{ $next['route'] }}" class="font-semibold text-amber-700 hover:underline">{{ $next['label'] }}</a></p>
    @else
        <p class="text-xs text-emerald-700 mt-3 font-medium">{{ __('borrower.kyc_progress.complete') }}</p>
    @endif
</div>

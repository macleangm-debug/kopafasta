@props(['customer', 'active' => 'personal'])

@php
    $profile = app(\App\Services\ProfileCompletionService::class)->calculate($customer);
    $percent = (int) ($profile['percent'] ?? 0);
    $sections = collect($profile['sections'] ?? [])->keyBy('key');
    $nidaVerified = app(\App\Services\NidaVerificationService::class)->isVerified($customer);
    $nidaUploaded = app(\App\Services\ProfileValidationService::class)->nationalIdUploadsComplete($customer);
    $faceVerified = ($customer->face_verification_status ?? '') === 'verified';
    $documentsComplete = app(\App\Services\ProfileCompletionService::class)->isDocumentsComplete($customer);

    $steps = [
        ['key' => 'nida', 'label' => __('borrower.kyc_progress.nida'), 'complete' => $nidaUploaded && $nidaVerified, 'route' => route('site.borrower.profile', ['section' => 'personal'])],
        ['key' => 'face', 'label' => __('borrower.kyc_progress.face'), 'complete' => $faceVerified, 'route' => route('site.borrower.face-verification')],
        ['key' => 'residence', 'label' => __('borrower.profile.residence'), 'complete' => (bool) ($sections['residence']['complete'] ?? false) && (! app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter() || app(\App\Services\ProfileValidationService::class)->hasResidenceLetter($customer)), 'route' => route('site.borrower.profile', ['section' => 'residence'])],
        ['key' => 'activity', 'label' => __('borrower.profile.activity'), 'complete' => (bool) ($sections['activity']['complete'] ?? false), 'route' => route('site.borrower.profile', ['section' => 'activity'])],
        ['key' => 'documents', 'label' => __('borrower.profile.documents_proof'), 'complete' => $documentsComplete, 'route' => route('site.borrower.profile', ['section' => 'kyc'])],
        ['key' => 'kin', 'label' => __('borrower.profile.kin'), 'complete' => app(\App\Services\ProfileValidationService::class)->isKinComplete($customer), 'route' => route('site.borrower.profile', ['section' => 'personal']).'#next-of-kin'],
    ];

    $next = collect($steps)->first(fn ($step) => ! $step['complete']);
    $completedCount = collect($steps)->where('complete', true)->count();
@endphp

<div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.kyc_progress.title') }}</p>
        <span class="text-xs font-semibold {{ $percent >= 100 ? 'text-emerald-700' : 'text-amber-700' }}">
            {{ __('borrower.kyc_progress.percent_complete', ['percent' => $percent]) }}
        </span>
    </div>
    <div class="h-2 rounded-full bg-gray-100 overflow-hidden mb-4">
        <div class="h-full rounded-full transition-all {{ $percent >= 100 ? 'bg-emerald-500' : 'bg-amber-500' }}" style="width: {{ min(100, max(0, $percent)) }}%"></div>
    </div>
    <ol class="flex flex-wrap items-center gap-2 text-sm">
        @foreach ($steps as $index => $step)
            @php
                $isActive = ($active === 'personal' && in_array($step['key'], ['nida', 'face', 'kin'], true))
                    || ($active === 'activity' && $step['key'] === 'activity')
                    || ($active === 'residence' && $step['key'] === 'residence')
                    || ($active === 'kyc' && $step['key'] === 'documents');
            @endphp
            <li class="flex items-center gap-2">
                @if ($index > 0)
                    <span class="text-gray-300 hidden sm:inline">→</span>
                @endif
                <a href="{{ $step['route'] }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 {{ $step['complete'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($isActive ? 'bg-amber-50 text-amber-900 ring-amber-200 font-semibold' : 'bg-gray-50 text-gray-700 ring-gray-200') }}">
                    <span>{{ $step['complete'] ? '✓' : ($index + 1) }}</span>
                    <span>{{ $step['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ol>
    @if ($next)
        <p class="text-xs text-gray-500 mt-3">
            {{ __('borrower.kyc_progress.next') }} ({{ $completedCount }}/{{ count($steps) }}):
            <a href="{{ $next['route'] }}" class="font-semibold text-amber-700 hover:underline">{{ $next['label'] }}</a>
        </p>
    @else
        <p class="text-xs text-emerald-700 mt-3 font-medium">{{ __('borrower.kyc_progress.complete') }}</p>
    @endif
</div>

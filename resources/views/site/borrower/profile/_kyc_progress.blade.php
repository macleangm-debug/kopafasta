@props(['customer', 'active' => 'personal', 'wizardMode' => false, 'wizardKey' => null])

@php
    $wizardService = app(\App\Services\ProfileWizardService::class);
    $onboarding = app(\App\Services\ApplicationRequirementsService::class)->onboardingBanner($customer);
    $percent = (int) ($onboarding['percent'] ?? 0);
    $stepHeading = null;
    $currentKey = $wizardKey;

    if ($wizardMode) {
        $wizardNav = $wizardService->navigation($customer, $wizardKey ?? 'nida');
        $progress = $wizardNav['progress'];
        $steps = collect($progress['steps'])->map(fn (array $step) => [
            'key'      => $step['key'],
            'label'    => $step['label'],
            'complete' => $step['complete'],
            'route'    => $step['url'],
        ])->all();
        $currentKey = $wizardKey ?? 'nida';
        $stepHeading = __('borrower.profile_wizard.step_of', [
            'current' => $wizardNav['index'] + 1,
            'total'   => $wizardNav['total'],
        ]).' — '.($wizardNav['current']['label'] ?? '');
        $next = $wizardNav['next'];
        $completedCount = (int) ($progress['completed'] ?? 0);
    } else {
        $profile = app(\App\Services\ProfileCompletionService::class)->calculate($customer);
        $sections = collect($profile['sections'] ?? [])->keyBy('key');
        $nidaUploaded = app(\App\Services\ProfileValidationService::class)->nationalIdUploadsComplete($customer);
        $nidaNumberSaved = filled($customer->national_id);
        $faceComplete = app(\App\Services\FaceVerificationService::class)->profileStepComplete($customer);
        $documentsComplete = app(\App\Services\ProfileCompletionService::class)->isDocumentsComplete($customer);

        $steps = [
            ['key' => 'nida', 'label' => __('borrower.kyc_progress.nida'), 'complete' => $nidaUploaded && $nidaNumberSaved, 'route' => route('site.borrower.profile', ['section' => 'personal'])],
            ['key' => 'face', 'label' => __('borrower.kyc_progress.face'), 'complete' => $faceComplete, 'route' => route('site.borrower.profile', ['section' => 'personal', 'focus' => 'face']).'#profile-face'],
            ['key' => 'residence', 'label' => __('borrower.profile.residence'), 'complete' => (bool) ($sections['residence']['complete'] ?? false) && (! app(\App\Services\ProfileValidationService::class)->requiresResidenceLetter() || app(\App\Services\ProfileValidationService::class)->hasResidenceLetter($customer)), 'route' => route('site.borrower.profile', ['section' => 'residence'])],
            ['key' => 'activity', 'label' => __('borrower.profile.activity'), 'complete' => (bool) ($sections['activity']['complete'] ?? false), 'route' => route('site.borrower.profile', ['section' => 'activity'])],
            ['key' => 'documents', 'label' => __('borrower.profile.documents_proof'), 'complete' => $documentsComplete, 'route' => route('site.borrower.profile', ['section' => 'kyc'])],
            ['key' => 'kin', 'label' => __('borrower.profile.kin'), 'complete' => app(\App\Services\ProfileValidationService::class)->isKinComplete($customer), 'route' => route('site.borrower.profile', ['section' => 'personal', 'focus' => 'kin'])],
        ];

        $next = collect($steps)->first(fn (array $step) => ! $step['complete']);
        $completedCount = collect($steps)->where('complete', true)->count();
    }
@endphp

<div class="mb-6 glass-card overflow-hidden relative">
    <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_top_right,_rgba(245,200,66,0.35),_transparent_55%)] pointer-events-none"></div>
    <div class="relative p-5 sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div>
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.kyc_progress.title') }}</p>
            @if ($stepHeading)
                <p class="text-sm text-gray-600 mt-0.5">{{ $stepHeading }}</p>
            @endif
        </div>
        <span class="text-xs font-semibold {{ $percent >= 100 ? 'text-emerald-700' : 'text-brand' }}">
            {{ __('borrower.kyc_progress.percent_complete', ['percent' => $percent]) }}
        </span>
    </div>
    <div class="h-2.5 rounded-full bg-gray-100 overflow-hidden mb-4">
        <div class="h-full rounded-full transition-all {{ $percent >= 100 ? 'bg-emerald-500' : 'bg-gradient-to-r from-brand to-brand-gold' }}" style="width: {{ min(100, max(0, $percent)) }}%"></div>
    </div>
    <ol class="flex flex-wrap items-center gap-2 text-sm">
        @foreach ($steps as $index => $step)
            @php
                $isActive = $wizardMode
                    ? ($step['key'] === $currentKey)
                    : (
                        ($active === 'personal' && in_array($step['key'], ['nida', 'face', 'kin'], true))
                        || ($active === 'activity' && $step['key'] === 'activity')
                        || ($active === 'residence' && $step['key'] === 'residence')
                        || ($active === 'kyc' && $step['key'] === 'documents')
                        || ($active === 'kin' && $step['key'] === 'kin')
                    );
            @endphp
            <li class="flex items-center gap-2">
                @if ($index > 0)
                    <span class="text-gray-300 hidden sm:inline">→</span>
                @endif
                <a href="{{ $step['route'] }}"
                   class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 {{ $step['complete'] ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($isActive ? 'bg-brand-muted text-brand ring-brand/25 font-semibold' : 'bg-white/80 text-gray-700 ring-gray-200') }}">
                    <span>{{ $step['complete'] ? '✓' : ($index + 1) }}</span>
                    <span>{{ $step['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ol>
    @if ($wizardMode && $next && ($next['key'] ?? null) !== $currentKey)
        <p class="text-xs text-gray-500 mt-3">
            {{ __('borrower.profile_wizard.up_next') }}:
            <a href="{{ $next['url'] }}" class="font-semibold text-brand hover:underline">{{ $next['label'] }}</a>
        </p>
    @elseif (! $wizardMode && $next)
        <p class="text-xs text-gray-500 mt-3">
            {{ __('borrower.kyc_progress.next') }} ({{ $completedCount }}/{{ count($steps) }}):
            <a href="{{ $next['route'] }}" class="font-semibold text-brand hover:underline">{{ $next['label'] }}</a>
        </p>
    @elseif ($percent >= 100)
        <p class="text-xs text-emerald-700 mt-3 font-medium">{{ __('borrower.kyc_progress.complete') }}</p>
    @endif
    </div>
</div>

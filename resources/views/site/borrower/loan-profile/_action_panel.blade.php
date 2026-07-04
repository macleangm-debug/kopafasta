@props(['profile'])

@php
    $status = $profile['status'] ?? [];
    $progress = $profile['progress'] ?? [];
    $next = $profile['next_action'] ?? [];
    $isDraft = (bool) ($profile['is_draft'] ?? false);
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-amber-100 text-amber-700',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
    ];
    $statusBadge = $toneClasses[$status['tone'] ?? 'gray'] ?? $toneClasses['gray'];
    $profilePercent = (int) ($progress['profile_percent'] ?? $progress['percent'] ?? 0);
    $profileComplete = (bool) ($progress['profile_complete'] ?? $profilePercent >= 100);
    $applicationLabel = $progress['application_status_label'] ?? ($status['label'] ?? '—');
    $applicationPercent = (int) ($progress['application_percent'] ?? $progress['percent'] ?? 0);
    $continueUrl = $next['url'] ?? ($profile['wizard_url'] ?? null);
    $continueLabel = $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form');
    $requirementLabels = collect($profile['missing_requirements'] ?? [])->pluck('label')->map(fn ($l) => mb_strtolower(trim((string) $l)))->all();
    $profileMissing = collect($progress['missing'] ?? [])
        ->filter(fn ($item) => ! in_array(mb_strtolower(trim((string) $item)), $requirementLabels, true))
        ->values()
        ->all();
@endphp

@if ($isDraft)
    <div class="mb-6 glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-gray-100/80">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.draft_summary_eyebrow') }}</p>
                    <h2 class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ __('borrower.loan_profile.draft_summary_title') }}</h2>
                    <p class="text-sm text-gray-600 mt-1">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.continue_form') }}</p>
                </div>
                @if ($continueUrl)
                    <a href="{{ $continueUrl }}"
                       class="inline-flex items-center justify-center font-bold px-8 py-3.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                        {{ $continueLabel }}
                    </a>
                @endif
            </div>

            <div class="mt-5 grid sm:grid-cols-2 gap-3">
                <div class="rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                    <div class="flex items-center gap-3 mt-2">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full {{ $profileComplete ? 'bg-emerald-500' : 'bg-brand' }}" style="width: {{ $profilePercent }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums {{ $profileComplete ? 'text-emerald-700' : 'text-gray-900' }}">
                            @if ($profileComplete)
                                ✓
                            @else
                                {{ $profilePercent }}%
                            @endif
                        </span>
                    </div>
                </div>
                <div class="rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.application_progress') }}</p>
                    <div class="flex items-center gap-3 mt-2">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $applicationPercent }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900">{{ $applicationPercent }}%</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">{{ $applicationLabel }}</p>
                </div>
            </div>
        </div>

        @if (! empty($progress['timeline']))
            <div class="px-5 sm:px-6 py-5 border-b border-gray-100/80">
                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-4">{{ __('borrower.loan_profile.application_progress') }}</p>
                <ol class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach ($progress['timeline'] as $index => $step)
                        @php
                            $complete = (bool) ($step['complete'] ?? false);
                            $current = (bool) ($step['current'] ?? false);
                            $stepClass = $complete
                                ? 'ring-emerald-200 bg-emerald-50'
                                : ($current ? 'ring-brand-gold/50 bg-amber-50' : 'ring-gray-200 bg-gray-50/80');
                            $dotClass = $complete ? 'bg-emerald-500 text-white' : ($current ? 'bg-brand-gold text-brand' : 'bg-gray-200 text-gray-500');
                        @endphp
                        <li class="rounded-xl ring-1 px-3 py-3 {{ $stepClass }}">
                            <div class="flex items-start gap-2.5">
                                <span class="size-6 rounded-full grid place-items-center text-xs font-bold shrink-0 {{ $dotClass }}">
                                    {{ $complete ? '✓' : ($index + 1) }}
                                </span>
                                <span class="text-sm {{ $current ? 'font-semibold text-gray-900' : 'text-gray-700' }}">{{ $step['label'] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif

        @if (! empty($profile['missing_requirements']) || $profileMissing !== [])
            <div class="px-5 sm:px-6 py-5">
                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">{{ __('borrower.loan_profile.missing_requirements_title') }}</p>
                <p class="text-xs text-gray-500 mb-4">{{ __('borrower.loan_profile.missing_requirements_hint') }}</p>
                <ul class="space-y-2">
                    @foreach ($profile['missing_requirements'] ?? [] as $requirement)
                        <li class="flex items-center justify-between gap-3 rounded-xl ring-1 ring-amber-200/80 bg-amber-50/60 px-4 py-3">
                            <span class="text-sm font-medium text-gray-900">{{ $requirement['label'] }}</span>
                            <a href="{{ $requirement['upload_url'] }}"
                               class="inline-flex items-center bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-xl text-xs shrink-0">
                                {{ __('borrower.loan_profile.upload') }}
                            </a>
                        </li>
                    @endforeach
                    @foreach ($profileMissing as $item)
                        <li class="flex items-center gap-2 rounded-xl ring-1 ring-gray-200/80 bg-white px-4 py-3 text-sm text-gray-700">
                            <span class="size-2 rounded-full bg-amber-500 shrink-0"></span>
                            <span>{{ $item }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @elseif (! empty($next['ready']))
            <div class="px-5 sm:px-6 py-4 bg-emerald-50/80 border-t border-emerald-100">
                <p class="text-sm font-semibold text-emerald-800">{{ __('borrower.loan_profile.application_ready') }}</p>
            </div>
        @endif

        @if ($continueUrl)
            <div class="px-5 sm:px-6 py-4 bg-gray-50/80 border-t border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-sm text-gray-600">{{ __('borrower.loan_profile.draft_continue_hint') }}</p>
                <a href="{{ $continueUrl }}"
                   class="inline-flex items-center justify-center font-semibold px-6 py-2.5 rounded-xl text-sm bg-brand hover:bg-brand-light text-white shrink-0">
                    {{ $continueLabel }} →
                </a>
            </div>
        @endif
    </div>
@elseif (($status['code'] ?? '') === 'rejected')
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand-muted/60 to-white ring-1 ring-brand/10 p-4 sm:p-5">
        <div id="rejection" class="rounded-xl bg-white ring-1 ring-red-100 px-4 py-4">
            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.current_status') }}</p>
            <p class="text-lg font-bold text-red-800 mt-1">{{ __('borrower.applications_list.statuses.not_approved') }}</p>
            @if (! empty($status['detail']))
                <p class="text-sm text-red-700 mt-2">{{ $status['detail'] }}</p>
            @endif
            @if (! empty($next['url']))
                <a href="{{ $next['url'] }}"
                   class="inline-flex items-center justify-center font-semibold px-5 py-2.5 rounded-full text-sm mt-4 bg-white ring-1 ring-red-200 hover:bg-red-50 text-red-800">
                    {{ $next['button_label'] ?? __('borrower.loan_profile.actions.view_reason') }}
                </a>
            @endif
        </div>
    </div>
@else
    <div class="mb-6 rounded-2xl bg-gradient-to-br from-brand-muted/60 to-white ring-1 ring-brand/10 p-4 sm:p-5">
        <div class="grid sm:grid-cols-2 gap-3 mb-4">
            <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                <p class="text-lg font-bold text-gray-900 mt-1">
                    @if ($profileComplete)
                        <span class="text-emerald-700">✓ {{ __('borrower.loan_profile.profile_complete') }}</span>
                    @else
                        {{ $profilePercent }}%
                    @endif
                </p>
            </div>
            <div class="rounded-xl bg-white/80 ring-1 ring-brand/10 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.application_progress') }}</p>
                <p class="text-lg font-bold text-gray-900 mt-1">{{ $applicationLabel }}</p>
                @if ($applicationPercent > 0)
                    <p class="text-xs text-gray-500 mt-0.5">{{ $applicationPercent }}%</p>
                @endif
            </div>
        </div>

        <div class="grid lg:grid-cols-[1fr_auto] gap-4 items-start">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.current_status') }}</p>
                    <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 {{ $statusBadge }}">{{ $status['label'] ?? '—' }}</span>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">{{ __('borrower.loan_profile.next_action_title') }}</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.continue_form') }}</p>
                    @if (! empty($next['ready']))
                        <p class="text-xs text-emerald-700 font-medium mt-1">{{ __('borrower.loan_profile.application_ready') }}</p>
                    @endif
                </div>
            </div>

            @if (! empty($next['url']) && ! in_array($next['code'] ?? '', ['under_review', 'view_application'], true))
                <a href="{{ $next['url'] }}"
                   class="inline-flex items-center justify-center font-semibold px-6 py-3 rounded-xl text-sm shrink-0 {{ ($next['tone'] ?? 'primary') === 'primary' ? 'bg-brand-gold hover:bg-yellow-400 text-brand font-bold' : 'bg-white ring-1 ring-gray-200/80 hover:bg-brand-muted/30 text-gray-800' }}">
                    {{ $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form') }}
                </a>
            @endif
        </div>
    </div>
@endif

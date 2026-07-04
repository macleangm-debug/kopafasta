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

            <div class="mt-5 rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
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

        @unless ($profileComplete)
            <div class="px-5 sm:px-6 py-5">
                <div class="rounded-xl ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 to-white p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <div class="flex-1 max-w-xs h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full bg-brand" style="width: {{ $profilePercent }}%"></div>
                                </div>
                                <span class="text-sm font-bold tabular-nums">{{ $profilePercent }}%</span>
                            </div>
                            <p class="text-sm text-gray-600 mt-2">{{ __('borrower.loan_profile.profile_completion_hint') }}</p>
                        </div>
                        <a href="{{ route('site.borrower.profile') }}"
                           class="inline-flex items-center justify-center font-semibold px-6 py-2.5 rounded-xl text-sm shrink-0 bg-brand hover:bg-brand-light text-white">
                            {{ __('borrower.loan_profile.complete_profile') }}
                        </a>
                    </div>
                </div>
            </div>
        @elseif (! empty($next['ready']))
            <div class="px-5 sm:px-6 py-4 bg-emerald-50/80 border-t border-emerald-100">
                <p class="text-sm font-semibold text-emerald-800">{{ __('borrower.loan_profile.application_ready') }}</p>
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
        <div class="grid lg:grid-cols-[1fr_auto] gap-4 items-start">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.current_status') }}</p>
                    <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 {{ $statusBadge }}">{{ $status['label'] ?? '—' }}</span>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">{{ __('borrower.loan_profile.next_action_title') }}</p>
                    <p class="text-sm font-semibold text-gray-900">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.continue_form') }}</p>
                </div>
            </div>
            @if (! empty($next['url']) && ! in_array($next['code'] ?? '', ['under_review', 'view_application'], true))
                <a href="{{ $next['url'] }}"
                   class="inline-flex items-center justify-center font-semibold px-6 py-3 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand font-bold">
                    {{ $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form') }}
                </a>
            @endif
        </div>
    </div>
@endif

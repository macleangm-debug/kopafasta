@props(['profile'])

@php
    $status = $profile['status'] ?? [];
    $progress = $profile['progress'] ?? [];
    $next = $profile['next_action'] ?? [];
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-amber-100 text-amber-700',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
    ];
    $statusBadge = $toneClasses[$status['tone'] ?? 'gray'] ?? $toneClasses['gray'];
    $btnClass = ($next['tone'] ?? 'primary') === 'primary'
        ? 'bg-brand-gold hover:bg-yellow-400 text-brand font-bold'
        : 'bg-white ring-1 ring-gray-200/80 hover:bg-brand-muted/30 text-gray-800';
    $profilePercent = (int) ($progress['profile_percent'] ?? $progress['percent'] ?? 0);
    $profileComplete = (bool) ($progress['profile_complete'] ?? $profilePercent >= 100);
    $applicationLabel = $progress['application_status_label'] ?? ($status['label'] ?? '—');
    $applicationPercent = (int) ($progress['application_percent'] ?? $progress['percent'] ?? 0);
@endphp

<div class="mb-6 rounded-2xl bg-gradient-to-br from-brand-muted/60 to-white ring-1 ring-brand/10 p-4 sm:p-5">
    @if (($status['code'] ?? '') === 'rejected')
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
    @else
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
            @if (! ($profile['is_draft'] ?? false) && $applicationPercent > 0)
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
               class="inline-flex items-center justify-center font-semibold px-6 py-3 rounded-xl text-sm shrink-0 {{ $btnClass }}">
                {{ $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form') }}
            </a>
        @endif
    </div>
    @endif
</div>

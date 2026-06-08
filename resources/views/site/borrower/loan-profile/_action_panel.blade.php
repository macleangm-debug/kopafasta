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
        ? 'bg-amber-500 hover:bg-amber-400 text-gray-900'
        : 'bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-800';
@endphp

<div class="mb-6 rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 to-white p-5 sm:p-6">
    <div class="grid lg:grid-cols-[1fr_auto] gap-5 items-start">
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <div>
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.current_status') }}</p>
                    <p class="text-lg font-bold text-gray-900 mt-0.5">{{ $status['label'] ?? '—' }}</p>
                </div>
                <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $statusBadge }}">{{ $status['label'] ?? '—' }}</span>
            </div>

            <div>
                <div class="flex items-center justify-between gap-3 mb-2">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.progress_title') }}</p>
                    <span class="text-sm font-bold text-amber-700">{{ $progress['percent'] ?? 0 }}%</span>
                </div>
                <div class="h-2 bg-white rounded-full overflow-hidden ring-1 ring-amber-100">
                    <div class="h-full bg-amber-500 transition-all" style="width: {{ $progress['percent'] ?? 0 }}%"></div>
                </div>
            </div>

            <div>
                <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold mb-1">{{ __('borrower.loan_profile.next_action_title') }}</p>
                <p class="text-sm font-semibold text-gray-900">{{ $next['label'] ?? __('borrower.loan_profile.next_actions.continue_form') }}</p>
                @if (! empty($next['ready']))
                    <p class="text-xs text-emerald-700 font-medium mt-1">{{ __('borrower.loan_profile.application_ready') }}</p>
                @endif
            </div>
        </div>

        @if (! empty($next['url']))
            <a href="{{ $next['url'] }}"
               class="inline-flex items-center justify-center font-semibold px-6 py-3 rounded-full text-sm shrink-0 {{ $btnClass }}">
                {{ $next['button_label'] ?? __('borrower.loan_profile.actions.continue_to_form') }}
            </a>
        @endif
    </div>
</div>

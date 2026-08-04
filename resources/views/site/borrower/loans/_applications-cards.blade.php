@php
    $rows = $rows ?? [];
    $toneClasses = $toneClasses ?? [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-brand-muted text-brand',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
        'orange'  => 'bg-orange-100 text-orange-700',
    ];
@endphp

<div class="grid sm:grid-cols-2 gap-4">
    @foreach ($rows as $row)
        @php
            $badge = $toneClasses[$row['status_tone']] ?? $toneClasses['sky'];
            $isClosed = ! empty($row['is_closed']) || in_array((string) ($row['status'] ?? ''), ['withdrawn', 'offer_declined', 'rejected'], true);
        @endphp
        <div class="glass-card p-5 {{ $isClosed ? 'opacity-90' : '' }}">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ $row['loan_type'] }}</p>
                    <p class="text-lg sm:text-xl font-bold text-gray-900 tracking-tight mt-0.5 leading-snug">{{ $row['product_name'] }}</p>
                    <p class="font-mono text-xs text-gray-500 mt-1">{{ $row['application_number'] }}</p>
                </div>
                <div class="flex flex-col items-end gap-1.5 shrink-0">
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $row['application_status'] ?? $row['status_label'] }}</span>
                    @if (! empty($row['underwriting_actions']))
                        <a href="{{ route('site.borrower.application', $row['id']) }}"
                           class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-900 ring-1 ring-amber-200 px-2 py-1 text-[10px] font-bold uppercase tracking-wide"
                           title="{{ __('borrower.loan_profile.uw_feedback_title') }}">
                            <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                            {{ count($row['underwriting_actions']) }}
                        </a>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
                <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.profile') }}</p>
                    <p class="font-semibold text-sm mt-0.5">
                        @if ($row['profile_complete'] ?? false)
                            <span class="text-emerald-700">{{ __('borrower.applications_list.profile_complete_check') }}</span>
                        @else
                            {{ $row['profile_percent'] ?? 0 }}%
                        @endif
                    </p>
                </div>
                <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.application') }}</p>
                    <p class="font-semibold text-sm mt-0.5">{{ $row['application_percent'] ?? 0 }}%</p>
                </div>
            </div>

            @if (! empty($row['progress_steps']) && ! $isClosed)
                <x-site.application-timeline
                    compact
                    :steps="$row['progress_steps']"
                    :percent="$row['application_percent'] ?? $row['progress_percent'] ?? null"
                    class="mb-4"
                />
            @endif

            @if (! empty($row['detail']))
                <p class="text-xs {{ $isClosed ? 'text-red-600' : 'text-gray-600' }} mb-3">{{ $row['detail'] }}</p>
            @endif

            @if (! empty($row['underwriting_actions']))
                <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200/80 px-3 py-2.5 flex items-center justify-between gap-2">
                    <p class="text-xs text-amber-950 font-semibold">{{ __('borrower.loan_profile.uw_feedback_title') }}</p>
                    <a href="{{ route('site.borrower.application', $row['id']) }}" class="text-xs font-bold text-brand hover:underline shrink-0">
                        {{ __('borrower.applications_list.view') }} →
                    </a>
                </div>
            @endif

            <div class="flex items-center gap-2 text-xs flex-wrap">
                <a href="{{ $row['action_url'] }}" class="inline-flex bg-brand hover:bg-brand-light text-white font-semibold px-4 py-2 rounded-xl text-sm">
                    {{ $row['action_label'] }}
                </a>
                @if ($row['is_draft'] ?? false)
                    @if (! empty($row['preview_url']))
                        <a href="{{ $row['preview_url'] }}" class="inline-flex bg-white hover:bg-brand-muted/30 text-gray-800 font-semibold px-4 py-2 rounded-xl text-sm ring-1 ring-gray-200/80">
                            {{ $row['preview_label'] ?? __('borrower.applications_list.view_application') }}
                        </a>
                    @endif
                @elseif (! empty($row['underwriting_actions']))
                    <a href="{{ route('site.borrower.application', $row['id']) }}"
                       class="inline-flex bg-white hover:bg-brand-muted/30 text-gray-800 font-semibold px-4 py-2 rounded-xl text-sm ring-1 ring-gray-200/80">
                        {{ __('borrower.applications_list.view') }}
                    </a>
                @endif
            </div>
        </div>
    @endforeach
</div>

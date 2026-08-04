@php
    $rows = $rows ?? [];
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-amber-100 text-amber-700',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
        'orange'  => 'bg-orange-100 text-orange-700',
    ];
@endphp

<section class="mb-10">
    <h2 class="text-lg font-semibold mb-1">{{ __('borrower.loans_page.section_applications') }}</h2>
    <p class="text-sm text-gray-500 mb-5">{{ __('borrower.loans_page.section_applications_hint') }}</p>

    @if ($rows === [])
        <x-site.empty-state
            icon="📋"
            :title="__('borrower.applications_list.empty_title')"
            :description="__('borrower.applications_list.empty_desc')"
            :action-label="__('borrower.loans_page.apply_new_cta')"
            :action-url="route('site.borrower.loan-products')"
        />
    @else
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach ($rows as $row)
                @php $badge = $toneClasses[$row['status_tone']] ?? $toneClasses['sky']; @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $row['loan_type'] }}</p>
                            <p class="text-lg font-bold text-gray-900 tracking-tight mt-0.5">{{ $row['product_name'] }}</p>
                            @if (! empty($row['application_number']))
                                <p class="font-mono text-xs text-gray-500 mt-1">{{ $row['application_number'] }}</p>
                            @endif
                        </div>
                        <div class="flex flex-col items-end gap-1.5 shrink-0">
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $row['status_label'] }}</span>
                            @if (! empty($row['underwriting_actions']))
                                <a href="{{ $row['action_url'] }}"
                                   class="inline-flex items-center gap-1 rounded-full bg-amber-100 text-amber-900 ring-1 ring-amber-200 px-2 py-1 text-[10px] font-bold"
                                   title="{{ __('borrower.loan_profile.uw_feedback_title') }}">
                                    <svg class="size-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                                    {{ count($row['underwriting_actions']) }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                            <span>{{ __('borrower.applications_list.progress') }}</span>
                            <span class="font-semibold">{{ $row['progress_percent'] }}%</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-amber-500" style="width: {{ $row['progress_percent'] }}%"></div>
                        </div>
                    </div>

                    <p class="text-[11px] text-gray-400 mb-4">
                        {{ __('borrower.applications_list.last_updated') }}: {{ optional($row['updated_at'])->format('d M Y') ?? '—' }}
                    </p>

                    @if (! empty($row['detail']))
                        <p class="text-xs {{ ($row['status'] ?? '') === 'rejected' ? 'text-red-600' : 'text-gray-600' }} mb-3">{{ $row['detail'] }}</p>
                    @endif

                    @if (! empty($row['underwriting_actions']))
                        <div class="mb-3 rounded-xl bg-amber-50 ring-1 ring-amber-200/80 px-3 py-2.5 flex items-center justify-between gap-2">
                            <p class="text-xs text-amber-950 font-semibold">{{ __('borrower.loan_profile.uw_feedback_title') }}</p>
                            <a href="{{ $row['action_url'] }}" class="text-xs font-bold text-brand hover:underline shrink-0">
                                {{ __('borrower.applications_list.view') }} →
                            </a>
                        </div>
                    @endif

                    <a href="{{ $row['action_url'] }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">
                        {{ $row['action_label'] }}
                    </a>
                </div>
            @endforeach
        </div>
    @endif
</section>

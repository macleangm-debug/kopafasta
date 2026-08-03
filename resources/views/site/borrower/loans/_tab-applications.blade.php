@php
    $rows = $applicationRows ?? [];
    $activeRows = collect($rows)->reject(fn ($row) => ! empty($row['is_closed']) || in_array((string) ($row['status'] ?? ''), [
        'withdrawn', 'offer_declined', 'rejected',
    ], true))->values()->all();
    $closedRows = collect($rows)->filter(fn ($row) => ! empty($row['is_closed']) || in_array((string) ($row['status'] ?? ''), [
        'withdrawn', 'offer_declined', 'rejected',
    ], true))->values()->all();
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-brand-muted text-brand',
        'sky'     => 'bg-sky-100 text-sky-700',
        'emerald' => 'bg-emerald-100 text-emerald-700',
        'red'     => 'bg-red-100 text-red-700',
        'orange'  => 'bg-orange-100 text-orange-700',
    ];
@endphp

<div class="flex items-center justify-between flex-wrap gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold">{{ __('borrower.applications_list.all_title') }}</h2>
        <p class="text-sm text-gray-500">{{ __('borrower.applications_list.all_hint') }}</p>
    </div>
    <div class="inline-flex rounded-xl ring-1 ring-gray-200/80 bg-white/80 p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ ($viewMode ?? 'table') === 'cards' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']) }}"
           class="px-3 py-1.5 rounded-lg font-semibold {{ ($viewMode ?? 'table') === 'table' ? 'bg-brand text-white' : 'text-gray-600 hover:bg-brand-muted/50' }}">
            {{ __('borrower.applications_list.table') }}
        </a>
    </div>
</div>

@if ($rows === [])
    <x-site.empty-state
        icon="📋"
        :title="__('borrower.applications_list.empty_title')"
        :description="__('borrower.applications_list.empty_desc')"
        :action-label="__('borrower.applications_list.empty_action')"
        :action-url="route('site.borrower.loan-products')"
    />
@elseif (($viewMode ?? 'table') === 'table')
    @foreach ([
        ['rows' => $activeRows, 'title' => __('borrower.applications_list.active_title'), 'hint' => __('borrower.applications_list.active_hint')],
        ['rows' => $closedRows, 'title' => __('borrower.applications_list.closed_title'), 'hint' => __('borrower.applications_list.closed_hint')],
    ] as $section)
        @continue($section['rows'] === [])
        <div class="mb-6">
            <div class="mb-3">
                <h3 class="text-sm font-bold text-gray-900">{{ $section['title'] }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $section['hint'] }}</p>
            </div>
            <div class="glass-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('borrower.applications_list.reference') }}</th>
                                <th class="px-4 py-3">{{ __('borrower.applications_list.product') }}</th>
                                <th class="px-4 py-3">{{ __('borrower.applications_list.profile') }}</th>
                                <th class="px-4 py-3">{{ __('borrower.applications_list.application') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('borrower.applications_list.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($section['rows'] as $row)
                                <tr class="hover:bg-brand-muted/20 cursor-pointer transition" onclick="window.location='{{ $row['action_url'] }}'">
                                    <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $row['application_number'] }}</td>
                                    <td class="px-4 py-3">{{ $row['product_name'] }}</td>
                                    <td class="px-4 py-3">
                                        @if ($row['profile_complete'] ?? false)
                                            <span class="text-emerald-700 font-semibold text-xs">{{ __('borrower.applications_list.profile_complete_check') }}</span>
                                        @else
                                            <span class="font-semibold text-xs">{{ $row['profile_percent'] ?? 0 }}%</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-xs text-gray-900">{{ $row['application_percent'] ?? 0 }}%</span>
                                        <span class="block text-[11px] text-gray-500 mt-0.5">{{ $row['application_status'] ?? $row['status_label'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <a href="{{ $row['action_url'] }}" class="text-brand font-semibold hover:underline text-xs">{{ $row['action_label'] }}</a>
                                        @if (($row['is_draft'] ?? false) && ! empty($row['preview_url']))
                                            <a href="{{ $row['preview_url'] }}" class="text-gray-600 font-semibold hover:underline text-xs">{{ $row['preview_label'] ?? __('borrower.applications_list.view_application') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach
@else
    @foreach ([
        ['rows' => $activeRows, 'title' => __('borrower.applications_list.active_title'), 'hint' => __('borrower.applications_list.active_hint')],
        ['rows' => $closedRows, 'title' => __('borrower.applications_list.closed_title'), 'hint' => __('borrower.applications_list.closed_hint')],
    ] as $section)
        @continue($section['rows'] === [])
        <div class="mb-8">
            <div class="mb-3">
                <h3 class="text-sm font-bold text-gray-900">{{ $section['title'] }}</h3>
                <p class="text-xs text-gray-500 mt-0.5">{{ $section['hint'] }}</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ($section['rows'] as $row)
                    @php $badge = $toneClasses[$row['status_tone']] ?? $toneClasses['sky']; @endphp
                    <div class="glass-card p-5 {{ ! empty($row['is_closed']) || in_array((string) ($row['status'] ?? ''), ['withdrawn', 'offer_declined', 'rejected'], true) ? 'opacity-90' : '' }}">
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
                                <p class="text-[11px] text-gray-500 mt-0.5">{{ $row['application_status'] ?? $row['status_label'] }}</p>
                            </div>
                        </div>

                        @if (! empty($row['progress_steps']) && ! in_array((string) ($row['status'] ?? ''), ['rejected', 'withdrawn', 'offer_declined'], true))
                            <x-site.application-timeline
                                compact
                                :steps="$row['progress_steps']"
                                :percent="$row['application_percent'] ?? $row['progress_percent'] ?? null"
                                class="mb-4"
                            />
                        @endif

                        @if (! empty($row['detail']))
                            <p class="text-xs {{ in_array((string) ($row['status'] ?? ''), ['rejected', 'withdrawn', 'offer_declined'], true) ? 'text-red-600' : 'text-gray-600' }} mb-3">{{ $row['detail'] }}</p>
                        @endif

                        @if (! empty($row['underwriting_actions']))
                            <div class="mb-4 rounded-xl bg-amber-50 ring-1 ring-amber-200/80 px-3 py-2.5 flex items-center justify-between gap-2">
                                <p class="text-xs text-amber-950 font-semibold">{{ __('borrower.loan_profile.uw_feedback_title') }}</p>
                                <a href="{{ route('site.borrower.application', $row['id']) }}" class="text-xs font-bold text-brand hover:underline shrink-0">
                                    {{ __('borrower.applications_list.view') }} →
                                </a>
                            </div>
                        @elseif (! empty($row['underwriting_active']))
                            <div class="mb-4 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-3 py-3">
                                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.dashboard.hero.under_review_title') }}</p>
                                <p class="text-xs text-gray-600 mt-1">{{ __('borrower.dashboard.hero.under_review_subtitle') }}</p>
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
                            @elseif (! empty($row['receipt_url']))
                                <a href="{{ $row['receipt_url'] }}" class="text-gray-500 hover:text-gray-700">{{ __('borrower.applications_list.receipt') }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif

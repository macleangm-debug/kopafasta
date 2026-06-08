@php
    $rows = $applicationRows ?? [];
    $toneClasses = [
        'gray'    => 'bg-gray-100 text-gray-700',
        'amber'   => 'bg-amber-100 text-amber-700',
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
    <div class="inline-flex rounded-lg ring-1 ring-gray-200 bg-white p-0.5 text-xs">
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'cards']) }}"
           class="px-3 py-1.5 rounded-md font-semibold {{ ($viewMode ?? 'cards') === 'cards' ? 'bg-amber-500 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']) }}"
           class="px-3 py-1.5 rounded-md font-semibold {{ ($viewMode ?? 'cards') === 'table' ? 'bg-amber-500 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
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
        :action-url="route('site.borrower.apply')"
    />
@elseif (($viewMode ?? 'cards') === 'table')
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.loan_type') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.reference') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.created') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.status') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.progress') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.current_step') }}</th>
                        <th class="px-4 py-3">{{ __('borrower.applications_list.last_updated') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('borrower.applications_list.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        @php $badge = $toneClasses[$row['status_tone']] ?? $toneClasses['sky']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">{{ $row['loan_type'] }}</td>
                            <td class="px-4 py-3">
                                <p class="font-mono text-xs">{{ $row['application_number'] }}</p>
                                <p class="text-xs text-gray-500">{{ $row['product_name'] }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ optional($row['created_at'])->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $row['status_label'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 min-w-[120px]">
                                    <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500" style="width: {{ $row['progress_percent'] }}%"></div>
                                    </div>
                                    <span class="text-xs text-gray-600 shrink-0">{{ $row['progress_percent'] }}%</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ $row['current_step'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ optional($row['updated_at'])->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ $row['action_url'] }}" class="text-amber-600 font-semibold hover:underline text-xs">{{ $row['action_label'] }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="grid sm:grid-cols-2 gap-4">
        @foreach ($rows as $row)
            @php $badge = $toneClasses[$row['status_tone']] ?? $toneClasses['sky']; @endphp
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ $row['loan_type'] }}</p>
                        <p class="font-mono font-semibold text-sm mt-0.5">{{ $row['application_number'] }}</p>
                        <p class="text-xs text-gray-500">{{ $row['product_name'] }}</p>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $row['status_label'] }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.created') }}</p>
                        <p class="font-semibold text-sm">{{ optional($row['created_at'])->format('d M Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.last_updated') }}</p>
                        <p class="font-semibold text-sm">{{ optional($row['updated_at'])->format('d M Y') ?? '—' }}</p>
                    </div>
                </div>

                @if (! empty($row['requested_amount']))
                    <div class="grid grid-cols-2 gap-3 text-sm mb-4">
                        <div>
                            <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.requested') }}</p>
                            <p class="font-semibold">{{ format_money($row['requested_amount']) }}</p>
                        </div>
                        @if (! empty($row['requested_tenure_months']))
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.tenure') }}</p>
                                <p class="font-semibold">{{ __('borrower.applications_list.tenure_months', ['count' => $row['requested_tenure_months']]) }}</p>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mb-4">
                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                        <span>{{ __('borrower.applications_list.progress') }}</span>
                        <span class="font-semibold">{{ $row['progress_percent'] }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-amber-500" style="width: {{ $row['progress_percent'] }}%"></div>
                    </div>
                    @if (! empty($row['progress_steps']))
                        <ul class="mt-2 space-y-0.5">
                            @foreach (array_slice($row['progress_steps'], 0, 4) as $step)
                                <li class="text-[11px] {{ ($step['complete'] ?? false) ? 'text-emerald-700' : 'text-gray-500' }}">
                                    {{ ($step['complete'] ?? false) ? '✓' : '○' }} {{ $step['label'] }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if (! empty($row['current_step']))
                    <p class="text-xs text-gray-600 mb-3">
                        <span class="font-medium text-gray-700">{{ __('borrower.applications_list.current_step') }}:</span>
                        {{ $row['current_step'] }}
                    </p>
                @endif

                @if (! empty($row['detail']))
                    <p class="text-xs {{ ($row['status'] ?? '') === 'rejected' ? 'text-red-600' : 'text-gray-600' }} mb-3">{{ $row['detail'] }}</p>
                @endif

                <div class="flex items-center gap-2 text-xs">
                    <a href="{{ $row['action_url'] }}" class="inline-flex bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">
                        {{ $row['action_label'] }}
                    </a>
                    @if (! ($row['is_draft'] ?? false) && ! empty($row['receipt_url']))
                        <a href="{{ $row['receipt_url'] }}" class="text-gray-500 hover:text-gray-700">{{ __('borrower.applications_list.receipt') }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif

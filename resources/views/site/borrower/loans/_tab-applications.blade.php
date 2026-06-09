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
           class="px-3 py-1.5 rounded-md font-semibold {{ ($viewMode ?? 'table') === 'cards' ? 'bg-amber-500 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
            {{ __('borrower.applications_list.cards') }}
        </a>
        <a href="{{ route('site.borrower.loans', ['tab' => 'applications', 'view' => 'table']) }}"
           class="px-3 py-1.5 rounded-md font-semibold {{ ($viewMode ?? 'table') === 'table' ? 'bg-amber-500 text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
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
@elseif (($viewMode ?? 'table') === 'table')
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
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
                    @foreach ($rows as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-xs font-semibold">{{ $row['application_number'] }}</td>
                            <td class="px-4 py-3">{{ $row['product_name'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['profile_complete'] ?? false)
                                    <span class="text-emerald-700 font-semibold text-xs">100%</span>
                                @else
                                    <span class="font-semibold text-xs">{{ $row['profile_percent'] ?? 0 }}%</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-medium text-gray-800">{{ $row['application_status'] ?? $row['status_label'] }}</span>
                            </td>
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
                    <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">{{ $row['application_status'] ?? $row['status_label'] }}</span>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.profile') }}</p>
                        <p class="font-semibold text-sm mt-0.5">
                            @if ($row['profile_complete'] ?? false)
                                <span class="text-emerald-700">✓ 100%</span>
                            @else
                                {{ $row['profile_percent'] ?? 0 }}%
                            @endif
                        </p>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5">
                        <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.application') }}</p>
                        <p class="font-semibold text-sm mt-0.5">{{ $row['application_status'] ?? $row['status_label'] }}</p>
                    </div>
                </div>

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

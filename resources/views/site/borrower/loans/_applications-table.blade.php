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

<div class="glass-card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">{{ __('borrower.applications_list.status') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.applications_list.reference') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.applications_list.product') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.applications_list.profile') }}</th>
                    <th class="px-4 py-3">{{ __('borrower.applications_list.application') }}</th>
                    <th class="px-4 py-3 text-right">{{ __('borrower.applications_list.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    @php
                        $badge = $toneClasses[$row['status_tone'] ?? 'sky'] ?? $toneClasses['sky'];
                        $viewUrl = ($row['is_draft'] ?? false)
                            ? ($row['preview_url'] ?? $row['action_url'])
                            : ($row['action_url'] ?? '#');
                    @endphp
                    <tr class="hover:bg-brand-muted/20 cursor-pointer transition" onclick="window.location='{{ $viewUrl }}'">
                        <td class="px-4 py-3">
                            <span class="inline-flex text-xs font-semibold rounded-full px-2.5 py-1 {{ $badge }}">
                                {{ $row['application_status'] ?? $row['status_label'] }}
                            </span>
                        </td>
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
                        </td>
                        <td class="px-4 py-3 text-right space-x-2" onclick="event.stopPropagation()">
                            @if ($row['is_draft'] ?? false)
                                <a href="{{ $row['action_url'] }}" class="text-brand font-semibold hover:underline text-xs">{{ $row['action_label'] }}</a>
                                @if (! empty($row['preview_url']))
                                    <a href="{{ $row['preview_url'] }}" class="text-gray-600 font-semibold hover:underline text-xs">{{ $row['preview_label'] ?? __('borrower.applications_list.view') }}</a>
                                @endif
                            @else
                                <a href="{{ $row['action_url'] }}" class="text-brand font-semibold hover:underline text-xs">{{ __('borrower.applications_list.view') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

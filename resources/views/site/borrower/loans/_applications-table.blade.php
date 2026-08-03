@php
    $rows = $rows ?? [];
@endphp

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
                @foreach ($rows as $row)
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

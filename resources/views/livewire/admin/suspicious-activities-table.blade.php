<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search activity or description…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="detected_at"   label="Detected" />
        <x-admin.th :sort="$sort" :direction="$direction" col="activity_type" label="Activity" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Customer</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="severity" label="Severity" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"   label="Status" />
        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Amount</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php($sc = ['low'=>'bg-emerald-50 text-emerald-700','medium'=>'bg-amber-50 text-amber-700','high'=>'bg-rose-50 text-rose-700','critical'=>'bg-rose-100 text-rose-800'][$r->severity] ?? 'bg-gray-100')
            @php($stc = ['open'=>'bg-amber-50 text-amber-700','investigating'=>'bg-brand-muted text-brand','cleared'=>'bg-emerald-50 text-emerald-700','reported'=>'bg-rose-50 text-rose-700','closed'=>'bg-gray-100 text-gray-600'][$r->status] ?? 'bg-gray-100')
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-gray-500 text-xs">{{ optional($r->detected_at)->format('Y-m-d H:i') }}</td>
                <td class="px-5 py-3"><a href="{{ route('admin.suspicious-activities.show', $r) }}" class="hover:text-brand font-medium">{{ $r->activity_type }}</a></td>
                <td class="px-5 py-3">{{ $r->customer ? trim(($r->customer->first_name ?? '').' '.($r->customer->last_name ?? '')) : '—' }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $sc }}">{{ ucfirst($r->severity) }}</span></td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $stc }}">{{ ucfirst($r->status) }}</span></td>
                <td class="px-5 py-3 text-right font-mono text-xs">{{ $r->amount ? format_number($r->amount, 0) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No suspicious activity reports.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

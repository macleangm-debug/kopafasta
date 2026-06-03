<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search reference, partner…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="settlement_date" label="Date" />
        <x-admin.th :sort="$sort" :direction="$direction" col="reference"       label="Reference" />
        <x-admin.th :sort="$sort" :direction="$direction" col="partner"         label="Partner" />
        <x-admin.th :sort="$sort" :direction="$direction" col="gross_amount"    label="Gross" />
        <x-admin.th :sort="$sort" :direction="$direction" col="net_amount"      label="Net" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"          label="Status" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-gray-500">{{ optional($r->settlement_date)->format('Y-m-d') }}</td>
                <td class="px-5 py-3 font-mono text-xs">{{ $r->reference ?? '—' }}</td>
                <td class="px-5 py-3">{{ $r->partner ?? '—' }}</td>
                <td class="px-5 py-3">{{ format_money( ($r->gross_amount ?? 0)) }}</td>
                <td class="px-5 py-3 font-semibold">{{ format_money( ($r->net_amount ?? 0)) }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'reconciled' => 'bg-emerald-100 text-emerald-800',
                        'pending'    => 'bg-amber-100 text-amber-800',
                        'disputed'   => 'bg-red-100 text-red-800',
                    ]" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No settlements found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

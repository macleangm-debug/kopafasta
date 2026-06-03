<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search settlement reference, partner…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="period_start" label="Period" />
        <x-admin.th :sort="$sort" :direction="$direction" col="settlement_id" label="Settlement" />
        <x-admin.th :sort="$sort" :direction="$direction" col="system_total"  label="System" />
        <x-admin.th :sort="$sort" :direction="$direction" col="bank_total"    label="Bank" />
        <x-admin.th :sort="$sort" :direction="$direction" col="variance"      label="Variance" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-xs text-gray-500">
                    {{ optional($r->period_start)->format('Y-m-d') }} → {{ optional($r->period_end)->format('Y-m-d') }}
                </td>
                <td class="px-5 py-3 font-mono text-xs">{{ $r->settlement?->reference ?? '—' }}</td>
                <td class="px-5 py-3">{{ format_money( ($r->system_total ?? 0)) }}</td>
                <td class="px-5 py-3">{{ format_money( ($r->bank_total ?? 0)) }}</td>
                <td class="px-5 py-3 @class(['font-semibold', 'text-red-700' => (float) ($r->variance ?? 0) !== 0.0])">
                    {{ format_money( ($r->variance ?? 0)) }}
                </td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'balanced' => 'bg-emerald-100 text-emerald-800',
                        'open'     => 'bg-blue-100 text-blue-800',
                        'variance' => 'bg-red-100 text-red-800',
                        'closed'   => 'bg-gray-100 text-gray-700',
                    ]" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No reconciliations found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

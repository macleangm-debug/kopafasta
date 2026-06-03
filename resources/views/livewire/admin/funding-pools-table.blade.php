<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search pool, lender…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="name"             label="Pool" />
        <x-admin.th :sort="$sort" :direction="$direction" col="lender_id"        label="Lender" />
        <x-admin.th :sort="$sort" :direction="$direction" col="amount_committed" label="Committed" />
        <x-admin.th :sort="$sort" :direction="$direction" col="amount_deployed"  label="Deployed" />
        <x-admin.th :sort="$sort" :direction="$direction" col="expected_yield"   label="Yield" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"           label="Status" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-medium">{{ $r->name }}</td>
                <td class="px-5 py-3">{{ $r->lender?->name ?? '—' }}</td>
                <td class="px-5 py-3">TZS {{ format_number( ($r->amount_committed ?? 0)) }}</td>
                <td class="px-5 py-3">TZS {{ format_number( ($r->amount_deployed ?? 0)) }}</td>
                <td class="px-5 py-3 text-xs text-gray-600">{{ format_number( ($r->expected_yield ?? 0), 2) }}%</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'open'     => 'bg-emerald-100 text-emerald-800',
                        'deployed' => 'bg-blue-100 text-blue-800',
                        'closed'   => 'bg-gray-100 text-gray-700',
                    ]" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No funding pools found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

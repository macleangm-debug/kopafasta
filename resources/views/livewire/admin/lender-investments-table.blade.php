<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search reference, lender…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="reference"     label="Reference" />
        <x-admin.th :sort="$sort" :direction="$direction" col="lender_id"     label="Lender" />
        <x-admin.th :sort="$sort" :direction="$direction" col="principal"     label="Principal" />
        <x-admin.th :sort="$sort" :direction="$direction" col="return_amount" label="Return" />
        <x-admin.th :sort="$sort" :direction="$direction" col="matures_at"    label="Matures" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->reference ?? '—' }}</td>
                <td class="px-5 py-3">{{ $r->lender?->name ?? '—' }}</td>
                <td class="px-5 py-3">TZS {{ number_format((float) ($r->principal ?? 0)) }}</td>
                <td class="px-5 py-3">TZS {{ number_format((float) ($r->return_amount ?? 0)) }}</td>
                <td class="px-5 py-3 text-gray-500">{{ optional($r->matures_at)->format('Y-m-d') ?? '—' }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'active'  => 'bg-emerald-100 text-emerald-800',
                        'matured' => 'bg-blue-100 text-blue-800',
                        'closed'  => 'bg-gray-100 text-gray-700',
                    ]" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No investments found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

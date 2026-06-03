<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search reference, loan #, channel…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="reference"  label="Reference" />
        <x-admin.th :sort="$sort" :direction="$direction" col="loan_id"    label="Loan" />
        <x-admin.th :sort="$sort" :direction="$direction" col="amount"     label="Amount" />
        <x-admin.th :sort="$sort" :direction="$direction" col="channel"    label="Channel" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"     label="Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="paid_at"    label="Paid at" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->reference ?? '—' }}</td>
                <td class="px-5 py-3">
                    <div class="font-medium">{{ $r->loan?->loan_number ?? '—' }}</div>
                    <div class="text-xs text-gray-500">
                        {{ trim(($r->loan?->customer?->first_name ?? '').' '.($r->loan?->customer?->last_name ?? '')) }}
                    </div>
                </td>
                <td class="px-5 py-3 font-semibold">{{ format_money( ($r->amount ?? 0)) }}</td>
                <td class="px-5 py-3 text-xs uppercase tracking-wide">{{ $r->channel ?? '—' }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'allocated' => 'bg-emerald-100 text-emerald-800',
                        'received'  => 'bg-blue-100 text-blue-800',
                        'reversed'  => 'bg-red-100 text-red-800',
                        'pending'   => 'bg-amber-100 text-amber-800',
                    ]" />
                </td>
                <td class="px-5 py-3 text-gray-500">
                    {{ optional($r->paid_at ?? $r->created_at)->format('Y-m-d H:i') }}
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No repayments found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

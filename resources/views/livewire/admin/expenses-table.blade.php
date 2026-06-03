<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search description, reference, category…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="expense_date" label="Date" />
        <x-admin.th :sort="$sort" :direction="$direction" col="reference"    label="Reference" />
        <x-admin.th :sort="$sort" :direction="$direction" col="category"     label="Category" />
        <x-admin.th :sort="$sort" :direction="$direction" col="amount"       label="Amount" />
        <x-admin.th :sort="$sort" :direction="$direction" col="branch_id"    label="Branch" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"       label="Status" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-gray-500">{{ optional($r->expense_date)->format('Y-m-d') }}</td>
                <td class="px-5 py-3 font-mono text-xs">{{ $r->reference ?? '—' }}</td>
                <td class="px-5 py-3">
                    <div class="font-medium">{{ $r->category ?? '—' }}</div>
                    <div class="text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($r->description, 40) }}</div>
                </td>
                <td class="px-5 py-3">{{ format_money( ($r->amount ?? 0)) }}</td>
                <td class="px-5 py-3 text-xs">{{ $r->branch?->name ?? '—' }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'paid'     => 'bg-emerald-100 text-emerald-800',
                        'approved' => 'bg-blue-100 text-blue-800',
                        'recorded' => 'bg-amber-100 text-amber-800',
                        'rejected' => 'bg-red-100 text-red-800',
                    ]" />
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No expenses found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

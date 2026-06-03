<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search code, name, email…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code"          label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name"          label="Lender" />
        <x-admin.th :sort="$sort" :direction="$direction" col="type"          label="Type" />
        <x-admin.th :sort="$sort" :direction="$direction" col="credit_limit"  label="Credit limit" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code ?? '—' }}</td>
                <td class="px-5 py-3">
                    <div class="font-medium">{{ $r->name }}</div>
                    <div class="text-xs text-gray-500">{{ $r->email }}</div>
                </td>
                <td class="px-5 py-3 text-xs uppercase">{{ $r->type ?? '—' }}</td>
                <td class="px-5 py-3">{{ format_money((float) ($r->credit_limit ?? 0)) }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'active'    => 'bg-emerald-100 text-emerald-800',
                        'inactive'  => 'bg-gray-100 text-gray-700',
                        'suspended' => 'bg-red-100 text-red-800',
                    ]" />
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-5 py-12 text-center text-gray-500">No lenders found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

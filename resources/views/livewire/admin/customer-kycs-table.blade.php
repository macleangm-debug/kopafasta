<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search customer name, phone, ID…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="customer_id" label="Customer" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"      label="KYC Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="reviewed_by" label="Reviewer" />
        <x-admin.th :sort="$sort" :direction="$direction" col="reviewed_at" label="Reviewed" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3">
                    <div class="font-medium">
                        {{ trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: '—' }}
                    </div>
                    <div class="text-xs text-gray-500">{{ $r->customer?->phone }}</div>
                </td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'approved'  => 'bg-emerald-100 text-emerald-800',
                        'in_review' => 'bg-blue-100 text-blue-800',
                        'pending'   => 'bg-amber-100 text-amber-800',
                        'rejected'  => 'bg-red-100 text-red-800',
                    ]" />
                </td>
                <td class="px-5 py-3 text-xs">{{ $r->reviewed_by ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-500">{{ optional($r->reviewed_at)->format('Y-m-d') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-5 py-12 text-center text-gray-500">No KYC records found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

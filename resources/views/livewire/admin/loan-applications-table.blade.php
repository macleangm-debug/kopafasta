<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" statusGroup="application_status" searchPlaceholder="Search application #, customer…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="application_number" label="App #" />
        <x-admin.th :sort="$sort" :direction="$direction" col="customer_id"        label="Customer" />
        <x-admin.th :sort="$sort" :direction="$direction" col="requested_amount"   label="Amount" />
        <x-admin.th :sort="$sort" :direction="$direction" col="status"             label="Status" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at"         label="Submitted" />
        <th class="px-5 py-2.5 text-right">Actions</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->application_number ?? '—' }}</td>
                <td class="px-5 py-3">
                    {{ trim(($r->customer?->first_name ?? '').' '.($r->customer?->last_name ?? '')) ?: '—' }}
                    <div class="text-xs text-gray-500">{{ $r->customer?->phone }}</div>
                </td>
                <td class="px-5 py-3">TZS {{ number_format((float) ($r->requested_amount ?? 0)) }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" group="application_status" :map="[
                        'approved'     => 'bg-emerald-100 text-emerald-800',
                        'pre_approved'   => 'bg-sky-100 text-sky-800',
                        'rejected'       => 'bg-red-100 text-red-800',
                        'in_progress'    => 'bg-blue-100 text-blue-800',
                        'submitted'      => 'bg-amber-100 text-amber-800',
                        'under_review'   => 'bg-blue-100 text-blue-800',
                        'awaiting_guarantor' => 'bg-purple-100 text-purple-800',
                    ]" />
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ display_label($r->current_stage, 'application_stage') }}</div>
                </td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.loan-applications.show', $r) }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">View →</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No applications found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

<div>
<x-admin.table-shell :records="$rows" :statuses="$statuses" searchPlaceholder="Search code, name, email…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code"          label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name"          label="Partner" />
        <x-admin.th :sort="$sort" :direction="$direction" col="type"          label="Type" />
        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Available</th>
        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Utilized</th>
        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Exposure</th>
        <x-admin.th :sort="$sort" :direction="$direction" col="status"        label="Status" />
        <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider"></th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php $m = $metricsService->forLender($r); @endphp
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code ?? '—' }}</td>
                <td class="px-5 py-3">
                    <a href="{{ route('admin.lenders.show', $r) }}" class="font-medium text-brand hover:text-brand-light">{{ $r->name }}</a>
                    <div class="text-xs text-gray-500">{{ $r->email }}</div>
                </td>
                <td class="px-5 py-3 text-xs uppercase">{{ $r->type ?? '—' }}</td>
                <td class="px-5 py-3 text-right font-mono">{{ format_money($m['capital_available']) }}</td>
                <td class="px-5 py-3 text-right font-mono">{{ format_money($m['capital_utilized']) }}</td>
                <td class="px-5 py-3 text-right font-mono">{{ format_money($m['outstanding_exposure']) }}</td>
                <td class="px-5 py-3">
                    <x-admin.badge :value="$r->status" :map="[
                        'active'    => 'bg-emerald-100 text-emerald-800',
                        'inactive'  => 'bg-gray-100 text-gray-700',
                        'suspended' => 'bg-red-100 text-red-800',
                    ]" />
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('admin.lenders.show', $r) }}" class="text-xs font-semibold text-brand hover:underline">View →</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No capital partners found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

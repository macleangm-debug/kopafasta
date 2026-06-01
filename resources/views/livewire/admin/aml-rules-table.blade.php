<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search AML rule…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code" label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name" label="Rule" />
        <x-admin.th :sort="$sort" :direction="$direction" col="rule_type" label="Type" />
        <x-admin.th :sort="$sort" :direction="$direction" col="action"   label="Action" />
        <x-admin.th :sort="$sort" :direction="$direction" col="severity" label="Severity" />
        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            @php($sc = ['low'=>'bg-emerald-50 text-emerald-700','medium'=>'bg-amber-50 text-amber-700','high'=>'bg-rose-50 text-rose-700','critical'=>'bg-rose-100 text-rose-800'][$r->severity] ?? 'bg-gray-100')
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code }}</td>
                <td class="px-5 py-3 font-medium"><a href="{{ route('admin.aml-rules.show', $r) }}" class="hover:text-indigo-600">{{ $r->name }}</a></td>
                <td class="px-5 py-3">{{ display_label($r->rule_type, 'aml_rule_type') }}</td>
                <td class="px-5 py-3 capitalize">{{ $r->action }}</td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $sc }}">{{ ucfirst($r->severity) }}</span></td>
                <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs {{ $r->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $r->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No AML rules yet.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

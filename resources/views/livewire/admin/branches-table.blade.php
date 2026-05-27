<div>
<x-admin.table-shell :records="$rows" searchPlaceholder="Search branch name or code…">
    <x-slot:headers>
        <x-admin.th :sort="$sort" :direction="$direction" col="code"       label="Code" />
        <x-admin.th :sort="$sort" :direction="$direction" col="name"       label="Branch" />
        <x-admin.th :sort="$sort" :direction="$direction" col="created_at" label="Created" />
    </x-slot:headers>
    <x-slot:rows>
        @forelse ($rows as $r)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs">{{ $r->code ?? '—' }}</td>
                <td class="px-5 py-3 font-medium">{{ $r->name }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $r->created_at?->format('Y-m-d') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-5 py-12 text-center text-gray-500">No branches found.</td></tr>
        @endforelse
    </x-slot:rows>
</x-admin.table-shell>
</div>

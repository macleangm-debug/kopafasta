<x-admin.layout
    title="Journal Entries"
    heading="Journal Entries"
    subheading="System-posted general ledger entries">

<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Total entries</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ format_number($entries->total()) }}</div>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Total debits</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ format_money($totalDr) }}</div>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <div class="text-xs uppercase tracking-wider text-gray-500">Total credits</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ format_money($totalCr) }}</div>
        </div>
    </div>

    <form method="GET" class="bg-white rounded-xl ring-1 ring-gray-200 p-4 mb-4 grid grid-cols-1 md:grid-cols-5 gap-3 text-sm">
        <input name="q" value="{{ request('q') }}" placeholder="Search number / description" class="border border-gray-300 rounded-lg px-3 py-2">
        <select name="status" class="border border-gray-300 rounded-lg px-3 py-2">
            <option value="">All statuses</option>
            @foreach (['posted','draft','reversed'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="border border-gray-300 rounded-lg px-3 py-2">
        <input type="date" name="to" value="{{ request('to') }}" class="border border-gray-300 rounded-lg px-3 py-2">
        <button class="bg-brand-gold text-brand rounded-lg px-3 py-2 font-semibold">Filter</button>
    </form>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase text-gray-500 bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-2">Entry #</th>
                    <th class="text-left px-4 py-2">Date</th>
                    <th class="text-left px-4 py-2">Description</th>
                    <th class="text-left px-4 py-2">Source</th>
                    <th class="text-right px-4 py-2">Debit</th>
                    <th class="text-right px-4 py-2">Credit</th>
                    <th class="text-left px-4 py-2">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($entries as $e)
                    <tr>
                        <td class="px-4 py-2 font-mono text-xs">{{ $e->entry_number }}</td>
                        <td class="px-4 py-2">{{ optional($e->entry_date)->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">{{ $e->description }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ class_basename($e->source_type ?? '') }}{{ $e->source_id ? ' #'.$e->source_id : '' }}</td>
                        <td class="px-4 py-2 text-right">{{ format_number((float) $e->total_debit) }}</td>
                        <td class="px-4 py-2 text-right">{{ format_number((float) $e->total_credit) }}</td>
                        <td class="px-4 py-2"><span @class([
                            'inline-flex px-2 py-0.5 rounded text-xs',
                            'bg-emerald-100 text-emerald-700' => $e->status === 'posted',
                            'bg-amber-100 text-amber-700'     => $e->status === 'draft',
                            'bg-gray-200 text-gray-700'       => $e->status === 'reversed',
                        ])>{{ $e->status }}</span></td>
                        <td class="px-4 py-2 text-right">
                            <a href="{{ route('admin.journal-entries.show', $e) }}" class="text-amber-700 font-semibold hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No journal entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-admin.layout>

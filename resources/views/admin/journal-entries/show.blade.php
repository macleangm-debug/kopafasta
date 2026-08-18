<x-admin.layout
    title="Journal Entry {{ $entry->entry_number }}"
    heading=""
    subheading="">

    <x-admin.letterhead
        kicker="Finance"
        :title="'Journal '.$entry->entry_number"
        :subtitle="$entry->description">
        <x-slot:actions>
            <a href="{{ route('admin.journal-entries.index') }}" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">Back to journal</a>
        </x-slot:actions>
    </x-admin.letterhead>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">Entry date</div>
            <div class="text-lg font-semibold text-gray-900">{{ optional($entry->entry_date)->format('Y-m-d') }}</div>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">Posted by</div>
            <div class="text-lg font-semibold text-gray-900">{{ $entry->postedBy?->name ?? 'System' }}</div>
            <div class="text-xs text-gray-500">{{ optional($entry->posted_at)->format('Y-m-d H:i') }}</div>
        </div>
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-4">
            <div class="text-xs uppercase text-gray-500">Source</div>
            <div class="text-lg font-semibold text-gray-900">
                {{ class_basename($entry->source_type ?? '—') }}{{ $entry->source_id ? ' #'.$entry->source_id : '' }}
            </div>
            <div class="text-xs text-gray-500">Status: <span class="capitalize">{{ $entry->status }}</span></div>
        </div>
    </div>

    @if ($entry->memo)
        <div class="bg-amber-50 ring-1 ring-amber-200 rounded-xl px-4 py-3 mb-4 text-sm text-amber-800">{{ $entry->memo }}</div>
    @endif

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase text-gray-500 bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-2 w-12">#</th>
                    <th class="text-left px-4 py-2">Account</th>
                    <th class="text-left px-4 py-2">Description</th>
                    <th class="text-right px-4 py-2">Debit</th>
                    <th class="text-right px-4 py-2">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($entry->lines as $line)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $line->line_no }}</td>
                        <td class="px-4 py-2">
                            <div class="font-medium text-gray-900">{{ $line->account?->name ?? '—' }}</div>
                            <div class="text-xs font-mono text-gray-500">{{ $line->account?->code }}</div>
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $line->description }}</td>
                        <td class="px-4 py-2 text-right">{{ (float) $line->debit > 0 ? format_number((float) $line->debit) : '' }}</td>
                        <td class="px-4 py-2 text-right">{{ (float) $line->credit > 0 ? format_number((float) $line->credit) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr class="border-t-2 border-gray-200">
                    <td colspan="3" class="px-4 py-2 text-right text-xs uppercase text-gray-500">Totals</td>
                    <td class="px-4 py-2 text-right">{{ format_number((float) $entry->total_debit) }}</td>
                    <td class="px-4 py-2 text-right">{{ format_number((float) $entry->total_credit) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</x-admin.layout>

<x-admin.layout title="Recovery Assignments" heading="Recovery Assignments" subheading="Partner cases linked to collection arrears">

    <div class="mb-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-4">
            <p class="text-[10px] uppercase text-gray-500">Open</p>
            <p class="text-2xl font-bold">{{ $counts['open'] }}</p>
        </div>
        <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-200 p-4">
            <p class="text-[10px] uppercase text-emerald-700">Completed</p>
            <p class="text-2xl font-bold text-emerald-900">{{ $counts['completed'] }}</p>
        </div>
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 p-4">
            <p class="text-[10px] uppercase text-amber-700">Escalated</p>
            <p class="text-2xl font-bold text-amber-900">{{ $counts['escalated'] }}</p>
        </div>
        <div class="rounded-xl bg-red-50 ring-1 ring-red-200 p-4">
            <p class="text-[10px] uppercase text-red-700">SLA breach</p>
            <p class="text-2xl font-bold text-red-900">{{ $counts['sla_breach'] }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Case</th>
                    <th class="px-5 py-3">Partner</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3">Outstanding</th>
                    <th class="px-5 py-3">SLA</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Charge</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($assignments as $assignment)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.recovery.assignments.show', $assignment) }}" class="text-amber-700 font-mono text-xs">
                                #{{ $assignment->id }}
                            </a>
                            <div class="text-xs text-gray-500">{{ $assignment->arrearCase?->loan?->loan_number }}</div>
                        </td>
                        <td class="px-5 py-3">{{ $assignment->vendor?->name }}</td>
                        <td class="px-5 py-3">{{ display_label($assignment->partner_type, 'recovery_partner_type') }}</td>
                        <td class="px-5 py-3">{{ format_money($assignment->original_outstanding) }}</td>
                        <td class="px-5 py-3 {{ $assignment->slaBreached() ? 'text-red-700 font-semibold' : '' }}">
                            {{ $assignment->sla_due_at?->format('d M Y') ?? '—' }}
                        </td>
                        <td class="px-5 py-3">{{ ucfirst(str_replace('_', ' ', $assignment->status)) }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ format_money($assignment->recovery_charge) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-5 py-12 text-center text-gray-500">No recovery assignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $assignments->links() }}</div>
</x-admin.layout>

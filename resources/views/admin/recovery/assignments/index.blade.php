<x-admin.layout title="Recovery Assignments" heading="" subheading="">

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-6 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Collections field</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Recovery assignments</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Partner cases linked to arrears — track SLA, escalations, and recovery charges.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Open</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['open']) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Completed</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['completed']) }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Escalated</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['escalated']) }}</p>
                </div>
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-rose-800 font-semibold">SLA breach</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['sla_breach']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="mb-4">
        <a href="{{ route('admin.partners.tasks') }}"
           class="inline-flex text-sm font-semibold text-brand hover:text-brand-light">
            Partner tasks (valuer / GPS / insurance) →
        </a>
    </div>

    <div class="bg-white rounded-2xl ring-1 ring-brand/10 shadow-sm overflow-hidden">
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
                            <a href="{{ route('admin.recovery.assignments.show', $assignment) }}" class="text-brand font-mono text-xs font-semibold">
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

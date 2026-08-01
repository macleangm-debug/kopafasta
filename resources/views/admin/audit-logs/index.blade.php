<x-admin.layout title="Audit Logs" heading="Audit Logs" subheading="Security events, admin changes, and borrower portal actions">
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">When</th>
                    <th class="px-5 py-3">Event</th>
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Entity</th>
                    <th class="px-5 py-3">IP</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2 text-xs text-gray-500">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</td>
                        <td class="px-5 py-2"><span class="rounded bg-brand-muted text-brand px-2 py-0.5 text-xs">{{ $log->event }}</span></td>
                        <td class="px-5 py-2">{{ optional($log->user)->name ?? 'System' }}</td>
                        <td class="px-5 py-2 text-xs font-mono">{{ class_basename($log->auditable_type) }}#{{ $log->auditable_id }}</td>
                        <td class="px-5 py-2 text-xs text-gray-500">{{ $log->ip_address ?? '—' }}</td>
                        <td class="px-5 py-2 text-right"><a href="{{ route('admin.audit-logs.show', $log->id) }}" class="text-xs font-semibold text-brand hover:text-brand-light">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No audit log entries.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50">
            {{ $logs->links() }}
        </div>
    </div>
</x-admin.layout>

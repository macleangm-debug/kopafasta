<x-admin.layout title="Audit Log Entry" heading="" subheading="">
    <x-admin.letterhead
        kicker="Audit"
        title="Audit log entry"
        :subtitle="'#'.$log->id">
        <x-slot:actions>
            <a href="{{ route('admin.audit-logs.index') }}" class="inline-flex items-center text-xs font-semibold text-brand bg-brand-gold hover:brightness-95 px-3 py-1.5 rounded-lg">Back to audit logs</a>
        </x-slot:actions>
    </x-admin.letterhead>

    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 mb-6">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs text-gray-500">When</dt><dd class="mt-0.5">{{ optional($log->created_at)->format('Y-m-d H:i:s') }}</dd></div>
            <div><dt class="text-xs text-gray-500">Event</dt><dd class="mt-0.5"><span class="rounded bg-brand-muted text-brand px-2 py-0.5 text-xs">{{ $log->event }}</span></dd></div>
            <div><dt class="text-xs text-gray-500">User</dt><dd class="mt-0.5">{{ optional($log->user)->name ?? 'System' }}</dd></div>
            <div><dt class="text-xs text-gray-500">Entity</dt><dd class="mt-0.5 font-mono text-xs">{{ class_basename($log->auditable_type) }}#{{ $log->auditable_id }}</dd></div>
            <div><dt class="text-xs text-gray-500">IP address</dt><dd class="mt-0.5">{{ $log->ip_address ?? '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-xs text-gray-500">User agent</dt><dd class="mt-0.5 text-xs text-gray-600 break-all">{{ $log->user_agent ?? '—' }}</dd></div>
        </dl>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Old values</h3>
            <pre class="bg-gray-50 rounded-lg p-4 text-xs overflow-x-auto text-gray-700">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' }}</pre>
        </div>
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">New values</h3>
            <pre class="bg-gray-50 rounded-lg p-4 text-xs overflow-x-auto text-gray-700">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '—' }}</pre>
        </div>
    </div>
</x-admin.layout>

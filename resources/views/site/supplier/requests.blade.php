<x-site.supplier-layout title="Asset requests" active="requests">
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Assigned asset requests</h1>
        <p class="text-sm text-gray-500 mt-1">Only requests an admin has approved and assigned to you appear here.</p>
    </div>
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @if ($requests->isEmpty())
        <x-site.empty-state
            icon="📋"
            title="No assigned requests yet"
            description="Admin-assigned asset requests will appear here when ready for you to accept."
        />
    @else
        <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Asset</th>
                        <th class="px-4 py-3 font-semibold">Budget</th>
                        <th class="px-4 py-3 font-semibold">Tenure</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach ($requests as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900">{{ $row->asset_name }}</td>
                            <td class="px-4 py-3 tabular-nums">{{ format_money($row->budget ?? 0) }}</td>
                            <td class="px-4 py-3">{{ $row->preferred_tenure_months ?? '—' }} mo</td>
                            <td class="px-4 py-3">{{ ucfirst($row->status) }}</td>
                            <td class="px-4 py-3">
                                @if ($row->status === 'reviewing')
                                    <div class="flex flex-wrap gap-3">
                                        <form method="POST" action="{{ route('site.supplier.requests.update', $row) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="accept">
                                            <button class="text-xs font-semibold text-emerald-700 hover:underline">Accept</button>
                                        </form>
                                        <form method="POST" action="{{ route('site.supplier.requests.update', $row) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="decline">
                                            <button class="text-xs font-semibold text-red-700 hover:underline">Decline</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $requests->links() }}</div>
    @endif
</x-site.supplier-layout>

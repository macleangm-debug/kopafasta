<x-site.supplier-layout title="Asset requests" active="requests">
    <h1 class="text-2xl font-bold mb-6">Assigned asset requests</h1>
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Budget</th><th class="px-4 py-3">Tenure</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row->asset_name }}</td>
                        <td class="px-4 py-3">{{ format_money($row->budget ?? 0) }}</td>
                        <td class="px-4 py-3">{{ $row->preferred_tenure_months ?? '—' }} mo</td>
                        <td class="px-4 py-3">{{ ucfirst($row->status) }}</td>
                        <td class="px-4 py-3">
                            @if (in_array($row->status, ['pending', 'reviewing'], true))
                                <form method="POST" action="{{ route('site.supplier.requests.update', $row) }}" class="flex flex-wrap gap-2 items-center">
                                    @csrf
                                    <input type="hidden" name="action" value="accept">
                                    <button class="text-xs font-semibold text-emerald-700">Accept</button>
                                </form>
                                <form method="POST" action="{{ route('site.supplier.requests.update', $row) }}" class="inline mt-1">
                                    @csrf
                                    <input type="hidden" name="action" value="decline">
                                    <button class="text-xs font-semibold text-red-700">Decline</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No requests assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $requests->links() }}</div>
</x-site.supplier-layout>

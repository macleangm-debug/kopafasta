<x-site.supplier-layout title="Asset requests" active="requests">
    <h1 class="text-2xl font-bold mb-6">Assigned asset requests</h1>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Budget</th><th class="px-4 py-3">Tenure</th><th class="px-4 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($requests as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row->asset_name }}</td>
                        <td class="px-4 py-3">TZS {{ number_format($row->budget ?? 0) }}</td>
                        <td class="px-4 py-3">{{ $row->preferred_tenure_months ?? '—' }} mo</td>
                        <td class="px-4 py-3">{{ ucfirst($row->status) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No requests assigned yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-site.supplier-layout>

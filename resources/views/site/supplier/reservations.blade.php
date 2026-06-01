<x-site.supplier-layout title="Reservations" active="reservations">
    <h1 class="text-2xl font-bold mb-6">Asset reservations</h1>
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Borrower</th><th class="px-4 py-3">Viewing</th><th class="px-4 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($reservations as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row->asset?->title }}</td>
                        <td class="px-4 py-3">{{ $row->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ optional($row->viewing_date)->format('d M Y') }} {{ $row->viewing_time }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($row->status)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">No reservations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-site.supplier-layout>

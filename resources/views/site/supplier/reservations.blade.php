<x-site.supplier-layout title="Reservations" active="reservations">
    <h1 class="text-2xl font-bold mb-6">Asset reservations</h1>
    @if (session('status'))<div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500"><tr><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Borrower</th><th class="px-4 py-3">Viewing</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($reservations as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row->asset?->title }}</td>
                        <td class="px-4 py-3">{{ $row->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ optional($row->viewing_date)->format('d M Y') }} {{ $row->viewing_time }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($row->status)) }}</td>
                        <td class="px-4 py-3">
                            @if ($row->status === 'viewing_scheduled')
                                <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="confirm_viewing">
                                    <button class="text-xs font-semibold text-amber-700 mr-2">Acknowledge</button>
                                </form>
                                <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="complete_viewing">
                                    <button class="text-xs font-semibold text-emerald-700">Mark viewed</button>
                                </form>
                            @elseif ($row->status === 'viewing_completed')
                                <span class="text-xs text-gray-500">Awaiting borrower confirmation</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No reservations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-site.supplier-layout>

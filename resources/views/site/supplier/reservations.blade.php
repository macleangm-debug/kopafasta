<x-site.supplier-layout title="Reservations" active="reservations">
    <x-site.borrower-page-header
        eyebrow="Supplier"
        title="Asset reservations"
        subtitle="Acknowledge viewings and advance handover milestones."
    />
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif
    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                <tr>
                    <th class="px-4 py-3 font-semibold">Asset</th>
                    <th class="px-4 py-3 font-semibold">Borrower</th>
                    <th class="px-4 py-3 font-semibold">Viewing</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($reservations as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $row->asset?->title }}</td>
                        <td class="px-4 py-3">{{ $row->customer?->full_name }}</td>
                        <td class="px-4 py-3">{{ optional($row->viewing_date)->format('d M Y') }} {{ $row->viewing_time }}</td>
                        <td class="px-4 py-3">{{ str_replace('_', ' ', ucfirst($row->status)) }}</td>
                        <td class="px-4 py-3">
                            @if ($row->status === 'viewing_scheduled')
                                <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="confirm_viewing">
                                    <button class="text-xs font-semibold text-brand hover:underline mr-2">Acknowledge</button>
                                </form>
                                <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="complete_viewing">
                                    <button class="text-xs font-semibold text-emerald-700 hover:underline">Mark viewed</button>
                                </form>
                            @elseif ($row->status === 'viewing_completed')
                                <span class="text-xs text-gray-500">Awaiting borrower confirmation</span>
                            @elseif ($row->status === 'post_approval_fees_paid')
                                <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="gps_installation">
                                    <button class="text-xs font-semibold text-brand hover:underline">GPS installed</button>
                                </form>
                            @elseif ($row->status === 'gps_installation')
                                <form method="POST" action="{{ route('site.supplier.reservations.update', $row) }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="action" value="insurance_active">
                                    <button class="text-xs font-semibold text-brand hover:underline">Insurance active</button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No reservations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $reservations->links() }}</div>
</x-site.supplier-layout>

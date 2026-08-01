<x-admin.layout title="Valuation Partners" heading="Valuation Partners" subheading="Asset valuers for asset-backed loan origination">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600 max-w-3xl">Assign valuers after the borrower pays the valuation fee. Valuers enter market and forced-sale values in their portal.</p>
        <a href="{{ route('admin.partners.create', ['category' => 'valuer']) }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">
            + New partner
        </a>
    </div>

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-5 py-3">Partner</th>
                    <th class="px-5 py-3">Phone</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($valuers as $valuer)
                    <tr>
                        <td class="px-5 py-3 font-semibold">{{ $valuer->name }}</td>
                        <td class="px-5 py-3">{{ $valuer->phone ?? '—' }}</td>
                        <td class="px-5 py-3 capitalize">{{ $valuer->status }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('admin.partners.show', $valuer) }}" class="text-amber-700 font-semibold hover:underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-gray-500">No valuation partners yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>

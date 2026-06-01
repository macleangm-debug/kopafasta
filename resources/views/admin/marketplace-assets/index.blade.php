<x-admin.layout title="Marketplace Assets" heading="Marketplace Assets" subheading="Asset lending inventory">
    <x-admin.index-toolbar route="admin.marketplace-assets" label="New asset" />
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Category</th>
                    <th class="px-4 py-3">Supplier</th>
                    <th class="px-4 py-3">Customer deposit</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse (\App\Models\MarketplaceAsset::query()->with('vendor')->latest()->limit(100)->get() as $asset)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $asset->title }}</td>
                        <td class="px-4 py-3">{{ config('asset_marketplace.categories.'.$asset->category, $asset->category) }}</td>
                        <td class="px-4 py-3">{{ $asset->supplier_name }}</td>
                        <td class="px-4 py-3">TZS {{ number_format($asset->customer_deposit ?: $asset->computeCustomerDeposit()) }}</td>
                        <td class="px-4 py-3">{{ $asset->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right"><a class="text-amber-700 font-semibold" href="{{ route('admin.marketplace-assets.show', $asset) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No marketplace assets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>

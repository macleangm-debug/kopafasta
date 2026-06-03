<x-site.supplier-layout title="Supplier assets" active="assets">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Marketplace assets</h1>
        <a href="{{ route('site.supplier.assets.create') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">Upload asset</a>
    </div>
    @if (session('status'))<div class="mb-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                <tr><th class="px-4 py-3">Title</th><th class="px-4 py-3">Deposit</th><th class="px-4 py-3">Weekly</th><th class="px-4 py-3">Status</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($assets as $asset)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $asset->title }}</td>
                        <td class="px-4 py-3">{{ format_money($asset->customer_deposit) }}</td>
                        <td class="px-4 py-3">{{ format_money($asset->weekly_installment) }}</td>
                        <td class="px-4 py-3">{{ $asset->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('site.supplier.assets.edit', $asset) }}" class="text-amber-700 font-semibold">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">No assets uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $assets->links() }}</div>
</x-site.supplier-layout>

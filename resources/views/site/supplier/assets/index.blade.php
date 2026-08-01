<x-site.supplier-layout title="Supplier assets" active="assets">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Marketplace assets</h1>
            <p class="text-sm text-gray-500 mt-1">Your listings — including delivered / released stock.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('site.supplier.delivered') }}"
               class="inline-flex bg-white ring-1 ring-gray-200 hover:ring-brand/30 text-gray-800 font-semibold px-4 py-2.5 rounded-xl text-sm">
                Delivered history
            </a>
            <a href="{{ route('site.supplier.assets.create') }}"
               class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-bold px-4 py-2.5 rounded-xl text-sm">
                Upload asset
            </a>
        </div>
    </div>
    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    <div class="glass-card rounded-2xl ring-1 ring-brand/10 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-brand-muted/30 text-left text-xs uppercase tracking-widest text-brand">
                <tr>
                    <th class="px-4 py-3 font-semibold">Title</th>
                    <th class="px-4 py-3 font-semibold">Deposit</th>
                    <th class="px-4 py-3 font-semibold">Weekly</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse ($assets as $asset)
                    <tr>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $asset->title }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ format_money($asset->customer_deposit) }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ format_money($asset->weekly_installment) }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'inline-flex text-xs font-semibold rounded-full px-2.5 py-1',
                                'bg-emerald-100 text-emerald-800' => $asset->is_active,
                                'bg-gray-100 text-gray-600' => ! $asset->is_active,
                            ])>{{ $asset->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('site.supplier.assets.edit', $asset) }}" class="text-brand font-semibold hover:underline">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">No assets uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $assets->links() }}</div>
</x-site.supplier-layout>

<x-admin.layout title="Marketplace Assets" heading="" subheading="">
    @php
        $canManage = auth()->user()?->hasPermission('marketplace.manage');
    @endphp

    <x-admin.letterhead
        kicker="Assets"
        title="Marketplace assets"
        subtitle="Listings customers can finance — keep photos, pricing, and availability current.">
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('admin.marketplace-assets.create') }}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl shadow-sm">
                    + New asset
                </a>
            @endif
        </x-slot:actions>
        <x-slot:stats>
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand/70 font-semibold">Listings</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($counts['total']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand/70 font-semibold">Active</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($counts['active']) }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-brand/70 font-semibold">Available</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1">{{ number_format($counts['available']) }}</p>
                </div>
            </div>
        </x-slot:stats>
    </x-admin.letterhead>

    <div class="bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 overflow-hidden">
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
                @forelse ($assets as $asset)
                    @php
                        $categoryMeta = config('asset_lending.categories.'.$asset->category);
                        $categoryLabel = is_array($categoryMeta)
                            ? ($categoryMeta['label'] ?? $asset->category)
                            : (config('asset_marketplace.categories.'.$asset->category, $asset->category));
                        if (is_array($categoryLabel)) {
                            $categoryLabel = $categoryLabel['label'] ?? $asset->category;
                        }
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ $asset->title }}</td>
                        <td class="px-4 py-3">{{ $categoryLabel }}</td>
                        <td class="px-4 py-3">{{ $asset->supplier_name }}</td>
                        <td class="px-4 py-3">{{ format_money($asset->customer_deposit ?: $asset->computeCustomerDeposit()) }}</td>
                        <td class="px-4 py-3">{{ $asset->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right space-x-3">
                            <a class="text-amber-700 font-semibold" href="{{ route('admin.marketplace-assets.show', $asset) }}">View</a>
                            @if ($canManage)
                                <a class="text-gray-600 font-semibold" href="{{ route('admin.marketplace-assets.edit', $asset) }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No marketplace assets yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>

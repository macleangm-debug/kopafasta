<x-admin.layout title="Approval Rules" heading="Approval Rules" subheading="Collateral, guarantor and workflow requirements per product">
    @php($products = \App\Models\LoanProduct::query()->orderBy('code')->get())

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-2.5 text-left">Product</th>
                    <th class="px-5 py-2.5 text-right">Amount cap</th>
                    <th class="px-5 py-2.5 text-center">Collateral</th>
                    <th class="px-5 py-2.5 text-center">Guarantor</th>
                    <th class="px-5 py-2.5 text-left">Workflow</th>
                    <th class="px-5 py-2.5 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.loan-products.show', $p) }}" class="font-medium text-gray-900 hover:text-amber-700">{{ $p->name }}</a>
                            <div class="text-xs text-gray-500 font-mono">{{ $p->code }}</div>
                        </td>
                        <td class="px-5 py-3 text-right">TZS {{ number_format((float) $p->max_amount) }}</td>
                        <td class="px-5 py-3 text-center">
                            @if ($p->requires_collateral)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">Required</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if ($p->requires_guarantor)
                                <span class="inline-flex px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-800">Required</span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $p->workflow?->name ?? 'Default committee' }}
                        </td>
                        <td class="px-5 py-3">
                            <x-admin.badge :value="$p->is_active ? 'active' : 'inactive'" :map="[
                                'active'   => 'bg-emerald-100 text-emerald-800',
                                'inactive' => 'bg-gray-100 text-gray-700',
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-gray-500">No loan products configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-gray-500">
        Workflow assignment is managed on each product's edit page. Customize approval committee, escalation rules and SLA thresholds there.
    </p>
</x-admin.layout>

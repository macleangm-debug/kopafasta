<x-admin.layout title="Loan Products" heading="Loan Products" subheading="Credit products offered to customers">
    @include('admin.settings._tabs', ['active' => 'loan-products'])

<x-admin.index-toolbar route="admin.loan-products" label="New product" />

    @php
        $products = \App\Models\LoanProduct::with('rateTiers')->orderBy('code')->get();
        $rateService = app(\App\Services\DisplayedRateService::class);
    @endphp

    <div class="bg-white rounded-2xl ring-1 ring-brand/10 overflow-hidden shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-gradient-to-r from-brand via-brand to-brand-light text-[11px] uppercase tracking-wider text-white/90">
                <tr>
                    <th class="px-5 py-3 text-left font-semibold">Code</th>
                    <th class="px-5 py-3 text-left font-semibold">Name</th>
                    <th class="px-5 py-3 text-left font-semibold">Category</th>
                    <th class="px-5 py-3 text-right font-semibold">Monthly rate</th>
                    <th class="px-5 py-3 text-right font-semibold">Tenure (m)</th>
                    <th class="px-5 py-3 text-right font-semibold">Amount range</th>
                    <th class="px-5 py-3 text-left font-semibold">Status</th>
                    <th class="px-5 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand/5">
                @forelse ($products as $p)
                    <tr class="hover:bg-brand-muted/30 transition">
                        <td class="px-5 py-3 font-mono text-xs text-brand">{{ $p->code }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $p->name }}</td>
                        <td class="px-5 py-3">{{ display_label((string) $p->category, 'product_category') }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ $rateService->formatBorrowerRateRange($p) }}</td>
                        <td class="px-5 py-3 text-right">{{ $p->tenure_min_months }}–{{ $p->tenure_max_months }}</td>
                        <td class="px-5 py-3 text-right">{{ format_number((float) $p->min_amount) }} – {{ format_number((float) $p->max_amount) }}</td>
                        <td class="px-5 py-3">
                            <x-admin.badge :value="$p->is_active ? 'active' : 'inactive'" :map="[
                                'active'   => 'bg-emerald-100 text-emerald-800',
                                'inactive' => 'bg-gray-100 text-gray-700',
                            ]" />
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.loan-products.show', $p) }}" class="text-xs font-semibold text-brand hover:underline">View</a>
                            <a href="{{ route('admin.loan-products.edit', $p) }}" class="ml-3 text-xs font-semibold text-gray-600 hover:text-brand">Edit</a>
                            <a href="{{ route('admin.loan-products.edit', $p) }}#documents" class="ml-3 text-xs font-medium text-gray-500 hover:text-brand">Docs</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No loan products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>

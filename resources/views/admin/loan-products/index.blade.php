<x-admin.layout title="Loan Products" heading="Loan Products" subheading="Credit products offered to customers">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    <x-admin.index-toolbar route="admin.loan-products" label="New product" />

    @php
        $products = \App\Models\LoanProduct::with('rateTiers')->orderBy('code')->get();
        $rateService = app(\App\Services\DisplayedRateService::class);
    @endphp

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-2.5 text-left">Code</th>
                    <th class="px-5 py-2.5 text-left">Name</th>
                    <th class="px-5 py-2.5 text-left">Category</th>
                    <th class="px-5 py-2.5 text-right">Monthly rate</th>
                    <th class="px-5 py-2.5 text-right">Tenure (m)</th>
                    <th class="px-5 py-2.5 text-right">Amount range</th>
                    <th class="px-5 py-2.5 text-left">Status</th>
                    <th class="px-5 py-2.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $p)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $p->code }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $p->name }}</td>
                        <td class="px-5 py-3">{{ display_label((string) $p->category, 'product_category') }}</td>
                        <td class="px-5 py-3 text-right font-semibold">{{ $rateService->formatBorrowerRateRange($p) }}</td>
                        <td class="px-5 py-3 text-right">{{ $p->tenure_min_months }}–{{ $p->tenure_max_months }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format((float) $p->min_amount) }} – {{ number_format((float) $p->max_amount) }}</td>
                        <td class="px-5 py-3">
                            <x-admin.badge :value="$p->is_active ? 'active' : 'inactive'" :map="[
                                'active'   => 'bg-emerald-100 text-emerald-800',
                                'inactive' => 'bg-gray-100 text-gray-700',
                            ]" />
                        </td>
                        <td class="px-5 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('admin.loan-products.show', $p) }}" class="text-xs font-medium text-amber-600 hover:text-amber-700">View</a>
                            <a href="{{ route('admin.loan-products.edit', $p) }}" class="ml-3 text-xs font-medium text-gray-600 hover:text-gray-900">Edit</a>
                            <a href="{{ route('admin.loan-products.edit', $p) }}#documents" class="ml-3 text-xs font-medium text-gray-500 hover:text-gray-800">Docs</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No loan products yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>

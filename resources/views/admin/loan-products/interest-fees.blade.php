<x-admin.layout title="Interest & Fees" heading="Interest & Fees" subheading="Pricing summary across all loan products">
    @php
        $products = \App\Models\LoanProduct::with('rateTiers')->orderBy('code')->get();
        $rateService = app(\App\Services\DisplayedRateService::class);
    @endphp

    <div class="bg-white rounded-xl ring-1 ring-gray-200 overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-5 py-2.5 text-left">Code</th>
                    <th class="px-5 py-2.5 text-left">Product</th>
                    <th class="px-5 py-2.5 text-right">Monthly rate</th>
                    <th class="px-5 py-2.5 text-right">Components</th>
                    <th class="px-5 py-2.5 text-right">Min amount</th>
                    <th class="px-5 py-2.5 text-right">Max amount</th>
                    <th class="px-5 py-2.5 text-right">Tenure</th>
                    <th class="px-5 py-2.5 text-left">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($products as $p)
                    @php $components = $rateService->rateComponents($p); @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3 font-mono text-xs">{{ $p->code }}</td>
                        <td class="px-5 py-3 font-medium text-gray-900">
                            <a href="{{ route('admin.loan-products.show', $p) }}" class="hover:text-amber-700">{{ $p->name }}</a>
                        </td>
                        <td class="px-5 py-3 text-right font-semibold">{{ $rateService->formatBorrowerRateRange($p) }}</td>
                        <td class="px-5 py-3 text-right text-xs text-gray-600">
                            {{ number_format($components['bot_regulated_rate'] * 100, 1) }} +
                            {{ number_format($components['processing_fee_rate'] * 100, 1) }} +
                            {{ number_format($components['service_fee_rate'] * 100, 1) }} +
                            {{ number_format($components['insurance_fee_rate'] * 100, 1) }}%
                        </td>
                        <td class="px-5 py-3 text-right">TZS {{ number_format((float) $p->min_amount) }}</td>
                        <td class="px-5 py-3 text-right">TZS {{ number_format((float) $p->max_amount) }}</td>
                        <td class="px-5 py-3 text-right">{{ $p->tenure_min_months }}–{{ $p->tenure_max_months }} mo</td>
                        <td class="px-5 py-3">
                            <x-admin.badge :value="$p->is_active ? 'active' : 'inactive'" :map="[
                                'active'   => 'bg-emerald-100 text-emerald-800',
                                'inactive' => 'bg-gray-100 text-gray-700',
                            ]" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-5 py-12 text-center text-gray-500">No loan products.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin.layout>

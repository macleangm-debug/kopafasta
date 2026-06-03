<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code"
    :backUrl="route('admin.loan-products.index')"
    :editUrl="route('admin.loan-products.edit', $record)"
    :fields="[
        'Code'                => $record->code,
        'Name'                => $record->name,
        'Category'            => display_label((string) $record->category, 'product_category'),
        'Monthly rate (borrower)' => app(\App\Services\DisplayedRateService::class)->formatBorrowerRateRange($record).' / month',
        'Application fee'     => ($record->application_fee_amount ?? 0) > 0
            ? 'TZS '.number_format((int) $record->application_fee_amount)
            : 'Global default',
        'Tenure (months)'     => $record->tenure_min_months.' – '.$record->tenure_max_months,
        'Repayment cadence'   => ucfirst($record->repayment_cadence ?? 'weekly'),
        'Min amount'          => 'TZS '.number_format((float) $record->min_amount),
        'Max amount'          => 'TZS '.number_format((float) $record->max_amount),
        'Requires collateral' => $record->requires_collateral ? 'Yes' : 'No',
        'Requires guarantor'  => $record->requires_guarantor ? 'Yes' : 'No',
        'Offer letter template' => $record->offerLetterTemplate?->name ?? 'System default',
        'Loan contract template' => $record->loanContractTemplate?->name ?? 'System default',
        'Status'              => $record->is_active ? 'Active' : 'Inactive',
        'Description'         => ['value' => $record->description, 'wide' => true],
        'Created'             => $record->created_at?->format('Y-m-d H:i'),
    ]">

    @php $components = app(\App\Services\DisplayedRateService::class)->rateComponents($record); @endphp
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-900 mb-3">Monthly rate components</h2>
        <dl class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 text-sm">
            <div><dt class="text-xs text-gray-500">BOT</dt><dd class="font-semibold">{{ number_format($components['bot_regulated_rate'] * 100, 2) }}%</dd></div>
            <div><dt class="text-xs text-gray-500">Processing</dt><dd class="font-semibold">{{ number_format($components['processing_fee_rate'] * 100, 2) }}%</dd></div>
            <div><dt class="text-xs text-gray-500">Risk</dt><dd class="font-semibold">{{ number_format($components['service_fee_rate'] * 100, 2) }}%</dd></div>
            <div><dt class="text-xs text-gray-500">Insurance</dt><dd class="font-semibold">{{ number_format($components['insurance_fee_rate'] * 100, 2) }}%</dd></div>
            <div><dt class="text-xs text-gray-500">Component total</dt><dd class="font-semibold">{{ number_format($components['component_total'] * 100, 2) }}%</dd></div>
        </dl>
    </div>

    @if ($record->rateTiers->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Tiered monthly rates</h2>
            <p class="text-xs text-gray-500 mb-4">Total monthly rate to borrower by approved amount (from loan configuration).</p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-100">
                        <tr>
                            <th class="text-left py-2 pr-4">Amount band (TZS)</th>
                            <th class="text-right py-2">Monthly rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach ($record->rateTiers as $tier)
                            <tr>
                                <td class="py-2 pr-4">{{ number_format((float) $tier->min_amount) }} – {{ number_format((float) $tier->max_amount) }}</td>
                                <td class="py-2 text-right font-semibold">{{ number_format((float) $tier->monthly_rate * 100, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Document requirements</h2>
                <p class="text-xs text-gray-500 mt-0.5">Shown to borrowers before and during application.</p>
            </div>
            <a href="{{ route('admin.loan-products.edit', $record) }}"
               class="text-xs font-semibold text-amber-700 hover:text-amber-800">Edit requirements →</a>
        </div>

        @if ($record->requirements->isNotEmpty())
            <ul class="divide-y divide-gray-100">
                @foreach ($record->requirements as $req)
                    <li class="py-3 first:pt-0 last:pb-0 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-medium text-sm text-gray-900">{{ $req->name }}</p>
                            @if ($req->description)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $req->description }}</p>
                            @endif
                        </div>
                        @if ($req->is_required)
                            <span class="text-[10px] uppercase tracking-wider font-semibold text-amber-700 bg-amber-50 px-2 py-0.5 rounded">Required</span>
                        @else
                            <span class="text-[10px] uppercase tracking-wider text-gray-500 bg-gray-100 px-2 py-0.5 rounded">Optional</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-sm text-gray-500">
                No document requirements configured.
                <a href="{{ route('admin.loan-products.edit', $record) }}" class="text-amber-700 font-medium hover:text-amber-800">Add some on the edit page</a>.
            </p>
        @endif
    </div>
</x-admin.show-page>

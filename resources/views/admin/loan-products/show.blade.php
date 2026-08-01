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
            ? format_money($record->application_fee_amount)
            : 'Global default',
        'Tenure (months)'     => $record->tenure_min_months.' – '.$record->tenure_max_months,
        'Repayment cadence'   => ucfirst($record->repayment_cadence ?? 'weekly'),
        'Min amount'          => format_money($record->min_amount),
        'Max amount'          => format_money($record->max_amount),
        'Grace after default' => ($record->default_grace_days ?? 7).' days',
        'Penalty'             => format_number((float) ($record->penalty_rate_percent ?? 1), 2).'% of amount owed '.str_replace('_', ' ', (string) ($record->penalty_basis ?? 'per_day')),
        'Requires collateral' => $record->requires_collateral ? 'Yes' : 'No',
        'Requires guarantor'  => $record->requires_guarantor ? 'Yes' : 'No',
        'Uses capital partner' => ($record->uses_capital_partner ?? true) ? 'Yes' : 'No',
        'Offer letter template' => $record->offerLetterTemplate?->name ?? 'System default',
        'Loan contract template' => $record->loanContractTemplate?->name ?? 'System default',
        'Status'              => $record->is_active ? 'Active' : 'Inactive',
        'Description'         => ['value' => $record->description, 'wide' => true],
        'Created'             => $record->created_at?->format('Y-m-d H:i'),
    ]">

    @if ($record->rateTiers->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-3">
            <h2 class="text-sm font-semibold text-gray-900">Tiered monthly rates</h2>
            <p class="text-xs text-gray-500">Expand a band to see BOT, processing, risk, and insurance components.</p>
            @foreach ($record->rateTiers as $tier)
                @php $parts = $tier->rateComponents(); @endphp
                <details class="rounded-lg ring-1 ring-gray-100 bg-gray-50/50">
                    <summary class="cursor-pointer px-4 py-3 flex flex-wrap justify-between gap-2 text-sm font-semibold text-gray-900">
                        <span>{{ format_money((float) $tier->min_amount) }} – {{ format_number((float) $tier->max_amount) }}</span>
                        <span class="text-amber-800">{{ format_number((float) $tier->monthly_rate * 100, 1) }}% / month</span>
                    </summary>
                    <div class="px-4 pb-4 pt-1 grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm border-t border-gray-100">
                        <div><span class="text-xs text-gray-500">BOT</span><p class="font-semibold">{{ format_number($parts['bot_regulated_rate'] * 100, 2) }}%</p></div>
                        <div><span class="text-xs text-gray-500">Processing</span><p class="font-semibold">{{ format_number($parts['processing_fee_rate'] * 100, 2) }}%</p></div>
                        <div><span class="text-xs text-gray-500">Risk</span><p class="font-semibold">{{ format_number($parts['service_fee_rate'] * 100, 2) }}%</p></div>
                        <div><span class="text-xs text-gray-500">Insurance</span><p class="font-semibold">{{ format_number($parts['insurance_fee_rate'] * 100, 2) }}%</p></div>
                    </div>
                </details>
            @endforeach
        </div>
    @endif

    @if ($record->postApprovalFees->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">Post-approval fees</h2>
            <p class="text-xs text-gray-500 mb-3">Pulled from <a href="{{ route('admin.charges-fees.index') }}" class="text-amber-700 font-semibold">fee management</a> and attached to this product.</p>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($record->postApprovalFees as $fee)
                    <li class="py-2 flex justify-between gap-3">
                        <span>{{ $fee->name }} <span class="text-xs text-gray-500">({{ $fee->code }})</span></span>
                        <span class="font-semibold text-gray-800">
                            @if ($fee->fee_type === 'percent')
                                {{ format_number((float) $fee->amount, 2) }}%
                            @else
                                {{ format_money((float) $fee->amount, 0) }}
                            @endif
                            · {{ $fee->is_active ? 'Active' : 'Off' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Document requirements</h2>
                <p class="text-xs text-gray-500 mt-0.5">Shown to borrowers before and during application.</p>
            </div>
            <a href="{{ route('admin.loan-products.edit', $record) }}"
               class="text-xs font-semibold text-brand hover:text-brand-light">Edit requirements →</a>
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
                <a href="{{ route('admin.loan-products.edit', $record) }}" class="text-amber-700 font-medium hover:text-brand-light">Add some on the edit page</a>.
            </p>
        @endif
    </div>
</x-admin.show-page>

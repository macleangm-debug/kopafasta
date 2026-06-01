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
        'Base interest rate'  => number_format((float) $record->interest_rate * 100, 2).' %',
        'BOT regulated rate'  => number_format((float) (app(\App\Services\DisplayedRateService::class)->breakdown($record)['bot_regulated_rate']) * 100, 2).' %',
        'Displayed rate'      => number_format((float) app(\App\Services\DisplayedRateService::class)->displayedMonthlyRate($record) * 100, 2).' % / month',
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

<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="optional(\App\Models\Lender::find($record->lender_id))->name"
    :backUrl="route('admin.funding-pools.index')"
    :editUrl="route('admin.funding-pools.edit', $record)"
    :fields="[
        'Lender'           => optional(\App\Models\Lender::find($record->lender_id))->name,
        'Currency'         => $record->currency,
        'Status'           => ucfirst($record->status ?? ''),
        'Amount committed' => $record->amount_committed !== null ? number_format((float) $record->amount_committed) : null,
        'Amount deployed'  => $record->amount_deployed  !== null ? number_format((float) $record->amount_deployed)  : null,
        'Expected yield'   => $record->expected_yield !== null ? (round((float) $record->expected_yield * 100, 2).'%') : null,
        'Start date'       => optional($record->start_date)->format('Y-m-d'),
        'End date'         => optional($record->end_date)->format('Y-m-d'),
        'Created'          => $record->created_at?->format('Y-m-d H:i'),
    ]" />

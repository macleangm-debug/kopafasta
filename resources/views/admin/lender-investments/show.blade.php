<x-admin.show-page
    :title="$record->reference"
    :heading="$record->reference ?: 'Investment'"
    :subheading="optional(\App\Models\Lender::find($record->lender_id))->name"
    :backUrl="route('admin.lender-investments.index')"
    :editUrl="route('admin.lender-investments.edit', $record)"
    :fields="[
        'Reference'      => $record->reference,
        'Lender'         => optional(\App\Models\Lender::find($record->lender_id))->name,
        'Funding pool'   => optional(\App\Models\FundingPool::find($record->funding_pool_id))->name,
        'Loan'           => optional(\App\Models\Loan::find($record->loan_id))->loan_number,
        'Status'         => ucfirst($record->status ?? ''),
        'Principal'      => $record->principal     !== null ? format_number((float) $record->principal)     : null,
        'Return amount'  => $record->return_amount !== null ? format_number((float) $record->return_amount) : null,
        'Return rate'    => $record->return_rate   !== null ? (round((float) $record->return_rate * 100, 2).'%') : null,
        'Invested at'    => optional($record->invested_at)->format('Y-m-d'),
        'Matures at'     => optional($record->matures_at)->format('Y-m-d'),
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]" />

<x-admin.show-page
    :title="$record->reference"
    :heading="$record->reference ?: 'Settlement'"
    :subheading="$record->partner"
    :backUrl="route('admin.settlements.index')"
    :editUrl="route('admin.settlements.edit', $record)"
    :fields="[
        'Reference'        => $record->reference,
        'Partner'          => $record->partner,
        'Settlement date'  => optional($record->settlement_date)->format('Y-m-d'),
        'Status'           => ucfirst($record->status ?? ''),
        'Gross amount'     => $record->gross_amount !== null ? format_number((float) $record->gross_amount) : null,
        'Fees'             => $record->fees !== null ? format_number((float) $record->fees) : null,
        'Net amount'       => $record->net_amount !== null ? format_number((float) $record->net_amount) : null,
        'Transactions'     => $record->transactions_count,
        'Notes'            => ['value' => $record->notes, 'wide' => true],
        'Created'          => $record->created_at?->format('Y-m-d H:i'),
    ]" />

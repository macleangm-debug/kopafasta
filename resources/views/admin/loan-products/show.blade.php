<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code"
    :backUrl="route('admin.loan-products.index')"
    :editUrl="route('admin.loan-products.edit', $record)"
    :fields="[
        'Code'                => $record->code,
        'Name'                => $record->name,
        'Category'            => str_replace('_', ' ', (string) $record->category),
        'Interest rate'       => number_format((float) $record->interest_rate * 100, 2).' %',
        'Tenure (months)'     => $record->tenure_min_months.' – '.$record->tenure_max_months,
        'Min amount'          => 'TZS '.number_format((float) $record->min_amount),
        'Max amount'          => 'TZS '.number_format((float) $record->max_amount),
        'Requires collateral' => $record->requires_collateral ? 'Yes' : 'No',
        'Requires guarantor'  => $record->requires_guarantor ? 'Yes' : 'No',
        'Status'              => $record->is_active ? 'Active' : 'Inactive',
        'Description'         => ['value' => $record->description, 'wide' => true],
        'Created'             => $record->created_at?->format('Y-m-d H:i'),
    ]" />

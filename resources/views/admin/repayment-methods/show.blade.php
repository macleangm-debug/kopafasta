<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.repayment-methods.index')"
    :editUrl="route('admin.repayment-methods.edit', $record)"
    :fields="[
        'Code' => $record->code, 'Name' => $record->name,
        'Channel' => str_replace('_',' ', $record->channel),
        'Fixed fee' => number_format($record->fixed_fee, 2),
        'Percentage fee' => number_format($record->percentage_fee * 100, 2).' %',
        'Auto reconcile' => $record->auto_reconcile ? 'Yes' : 'No',
        'Status' => $record->is_active ? 'Active' : 'Inactive',
    ]" />

<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.disbursement-methods.index')"
    :editUrl="route('admin.disbursement-methods.edit', $record)"
    :fields="[
        'Code' => $record->code, 'Name' => $record->name,
        'Channel' => str_replace('_',' ', $record->channel),
        'Fixed fee' => number_format($record->fixed_fee, 2),
        'Percentage fee' => number_format($record->percentage_fee * 100, 2).' %',
        'Priority' => $record->priority,
        'Status' => $record->is_active ? 'Active' : 'Inactive',
    ]" />

<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.charges-fees.index')"
    :editUrl="route('admin.charges-fees.edit', $record)"
    :fields="[
        'Code' => $record->code, 'Name' => $record->name,
        'Type'  => str_replace('_',' ', $record->type),
        'Basis' => str_replace('_',' ', $record->basis),
        'Amount / rate' => number_format($record->amount, 4),
        'Min amount' => $record->min_amount ? number_format($record->min_amount, 2) : '—',
        'Max amount' => $record->max_amount ? number_format($record->max_amount, 2) : '—',
        'When' => $record->charge_when,
        'GL account' => $record->glAccount->name ?? '—',
        'Status' => $record->is_active ? 'Active' : 'Inactive',
        'Description' => ['value' => $record->description, 'wide' => true],
    ]" />

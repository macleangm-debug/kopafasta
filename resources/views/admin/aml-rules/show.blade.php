<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.aml-rules.index')"
    :editUrl="route('admin.aml-rules.edit', $record)"
    :fields="[
        'Code' => $record->code, 'Name' => $record->name,
        'Rule type' => str_replace('_',' ', $record->rule_type),
        'Threshold amount' => $record->threshold_amount ? number_format($record->threshold_amount, 2) : '—',
        'Threshold count'  => $record->threshold_count ?? '—',
        'Window (days)'    => $record->window_days ?? '—',
        'Action'   => ucfirst($record->action),
        'Severity' => ucfirst($record->severity),
        'Status'   => $record->is_active ? 'Active' : 'Inactive',
        'Description' => ['value' => $record->description, 'wide' => true],
    ]" />

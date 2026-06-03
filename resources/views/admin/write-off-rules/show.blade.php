<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->days_past_due.' DPD'"
    :backUrl="route('admin.write-off-rules.index')"
    :editUrl="route('admin.write-off-rules.edit', $record)"
    :fields="[
        'Name' => $record->name,
        'DPD ≥' => $record->days_past_due.' days',
        'Min outstanding' => $record->min_outstanding ? format_number($record->min_outstanding, 2) : '—',
        'Max outstanding' => $record->max_outstanding ? format_number($record->max_outstanding, 2) : '—',
        'Committee approval' => $record->require_committee_approval ? 'Required' : 'Not required',
        'Auto propose' => $record->auto_propose ? 'Yes' : 'No',
        'Status' => $record->is_active ? 'Active' : 'Inactive',
        'Description' => ['value' => $record->description, 'wide' => true],
    ]" />

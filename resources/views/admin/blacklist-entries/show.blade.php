<x-admin.show-page
    :title="$record->identifier_value" :heading="$record->identifier_value" :subheading="ucfirst($record->identifier_type)"
    :backUrl="route('admin.blacklist-entries.index')"
    :editUrl="route('admin.blacklist-entries.edit', $record)"
    :fields="[
        'Type'   => ucfirst($record->identifier_type),
        'Value'  => $record->identifier_value,
        'Reason' => $record->reason,
        'Source' => ucfirst($record->source),
        'Listed on'  => optional($record->listed_on)->format('Y-m-d') ?? '—',
        'Expires on' => optional($record->expires_on)->format('Y-m-d') ?? 'Never',
        'Added by' => $record->addedBy->name ?? '—',
        'Status' => $record->is_active ? 'Listed' : 'Cleared',
        'Notes'  => ['value' => $record->notes, 'wide' => true],
    ]" />

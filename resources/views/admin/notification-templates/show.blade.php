<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.notification-templates.index')"
    :editUrl="route('admin.notification-templates.edit', $record)"
    :fields="[
        'Code'    => $record->code,
        'Name'    => $record->name,
        'Channel' => display_label($record->channel, 'channel'),
        'Subject' => $record->subject ?? '—',
        'Status'  => $record->is_active ? 'Active' : 'Inactive',
        'Body'    => ['value' => $record->body, 'wide' => true],
    ]" />

<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.roles.index')"
    :editUrl="route('admin.roles.edit', $record)"
    :fields="[
        'Code'        => $record->code,
        'Name'        => $record->name,
        'System role' => $record->is_system ? 'Yes' : 'No',
        'Description' => ['value' => $record->description, 'wide' => true],
        'Permissions' => ['value' => is_array($record->permissions) ? implode(', ', $record->permissions) : '—', 'wide' => true],
        'Created'     => $record->created_at?->format('Y-m-d H:i'),
    ]" />

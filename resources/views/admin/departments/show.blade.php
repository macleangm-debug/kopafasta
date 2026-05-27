<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.departments.index')"
    :editUrl="route('admin.departments.edit', $record)"
    :fields="[
        'Code'        => $record->code,
        'Name'        => $record->name,
        'Branch'      => $record->branch->name ?? '—',
        'Head'        => $record->head->name ?? '—',
        'Status'      => $record->is_active ? 'Active' : 'Inactive',
        'Description' => ['value' => $record->description, 'wide' => true],
        'Created'     => $record->created_at?->format('Y-m-d H:i'),
    ]" />

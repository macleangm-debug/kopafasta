<x-admin.show-page
    :title="$record->name" :heading="$record->name" :subheading="$record->code"
    :backUrl="route('admin.document-templates.index')"
    :editUrl="route('admin.document-templates.edit', $record)"
    :fields="[
        'Code'   => $record->code,
        'Name'   => $record->name,
        'Status' => $record->is_active ? 'Active' : 'Inactive',
        'Content' => ['value' => $record->content, 'wide' => true],
    ]" />

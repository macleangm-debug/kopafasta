<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->code"
    :backUrl="route('admin.branches.index')"
    :editUrl="route('admin.branches.edit', $record)"
    :fields="[
        'Branch code' => $record->code,
        'Name'        => $record->name,
        'Region'      => $record->region,
        'Status'      => $record->is_active ? 'Active' : 'Inactive',
        'Phone'       => $record->phone,
        'Email'       => $record->email,
        'Address'     => ['value' => $record->address, 'wide' => true],
        'Created'     => $record->created_at?->format('Y-m-d H:i'),
    ]" />

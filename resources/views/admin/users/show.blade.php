<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->email"
    :backUrl="route('admin.users.index')"
    :editUrl="route('admin.users.edit', $record)"
    :fields="[
        'Name'           => $record->name,
        'Email'          => $record->email,
        'Phone'          => $record->phone,
        'Role'           => ucfirst(str_replace('_', ' ', $record->role ?? '')),
        'Branch'         => optional(\App\Models\Branch::find($record->branch_id))->name,
        'Approval limit' => $record->approval_limit ? 'TZS '.number_format((float) $record->approval_limit) : null,
        'Status'         => $record->is_active ? 'Active' : 'Inactive',
        'Created'        => $record->created_at?->format('Y-m-d H:i'),
    ]" />

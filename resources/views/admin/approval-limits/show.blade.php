<x-admin.show-page
    :title="$record->role_code.' / '.$record->action"
    :heading="$record->role_code"
    :subheading="$record->action"
    :backUrl="route('admin.approval-limits.index')"
    :editUrl="route('admin.approval-limits.edit', $record)"
    :fields="[
        'Role code'     => $record->role_code,
        'Action'        => $record->action,
        'Min amount'    => number_format($record->min_amount, 2).' '.$record->currency,
        'Max amount'    => number_format($record->max_amount, 2).' '.$record->currency,
        'Dual control'  => $record->requires_dual_control ? 'Required' : 'Not required',
        'Status'        => $record->is_active ? 'Active' : 'Inactive',
        'Created'       => $record->created_at?->format('Y-m-d H:i'),
    ]" />

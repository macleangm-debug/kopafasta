<x-admin.show-page
    :title="display_label($record->role_code, 'role').' / '.display_label($record->action, 'approval_action')"
    :heading="display_label($record->role_code, 'role')"
    :subheading="display_label($record->action, 'approval_action')"
    :backUrl="route('admin.approval-limits.index')"
    :editUrl="route('admin.approval-limits.edit', $record)"
    :fields="[
        'Role'          => display_label($record->role_code, 'role'),
        'Role code'     => $record->role_code,
        'Action'        => $record->action,
        'Min amount'    => number_format($record->min_amount, 2).' '.$record->currency,
        'Max amount'    => number_format($record->max_amount, 2).' '.$record->currency,
        'Dual control'  => $record->requires_dual_control ? 'Required' : 'Not required',
        'Status'        => $record->is_active ? 'Active' : 'Inactive',
        'Created'       => $record->created_at?->format('Y-m-d H:i'),
    ]" />

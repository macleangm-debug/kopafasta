<x-admin.show-page
    :title="'STR #'.$record->id" :heading="$record->activity_type" :subheading="optional($record->detected_at)->format('Y-m-d H:i')"
    :backUrl="route('admin.suspicious-activities.index')"
    :editUrl="route('admin.suspicious-activities.edit', $record)"
    :fields="[
        'Activity type' => $record->activity_type,
        'Amount'        => $record->amount ? number_format($record->amount, 2) : '—',
        'Severity'      => ucfirst($record->severity),
        'Status'        => ucfirst($record->status),
        'Detected'      => optional($record->detected_at)->format('Y-m-d H:i'),
        'Resolved'      => optional($record->resolved_at)->format('Y-m-d H:i') ?? '—',
        'Customer'      => $record->customer ? trim(($record->customer->first_name ?? '').' '.($record->customer->last_name ?? '')) : '—',
        'Loan'          => $record->loan->loan_number ?? '—',
        'AML rule'      => $record->rule->name ?? '—',
        'Assigned to'   => $record->assignee->name ?? '—',
        'Description'   => ['value' => $record->description, 'wide' => true],
        'Investigator notes' => ['value' => $record->investigator_notes, 'wide' => true],
    ]" />

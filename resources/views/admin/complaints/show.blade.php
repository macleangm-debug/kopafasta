@php
    $customer = \App\Models\Customer::find($record->customer_id);
    $agent    = \App\Models\User::find($record->handled_by);
@endphp
<x-admin.show-page
    :title="$record->complaint_number"
    :heading="$record->subject ?: 'Complaint'"
    :subheading="$record->complaint_number"
    :backUrl="route('admin.complaints.index')"
    :editUrl="route('admin.complaints.edit', $record)"
    :fields="[
        'Complaint #'      => $record->complaint_number,
        'Customer'         => $customer ? trim($customer->first_name.' '.$customer->last_name) : null,
        'Handled by'       => $agent?->name,
        'Severity'         => ucfirst($record->severity ?? ''),
        'Status'           => display_label($record->status, 'complaint_status'),
        'Channel'          => display_label($record->channel, 'channel'),
        'Resolved at'      => optional($record->resolved_at)->format('Y-m-d H:i'),
        'Description'      => ['value' => $record->description, 'wide' => true],
        'Resolution notes' => ['value' => $record->resolution_notes, 'wide' => true],
        'Created'          => $record->created_at?->format('Y-m-d H:i'),
    ]" />

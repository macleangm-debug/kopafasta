@php
    $customer = \App\Models\Customer::find($record->customer_id);
    $agent    = \App\Models\User::find($record->assigned_to);
@endphp
<x-admin.show-page
    :title="$record->ticket_number"
    :heading="$record->subject ?: 'Ticket'"
    :subheading="$record->ticket_number"
    :backUrl="route('admin.support-tickets.index')"
    :editUrl="route('admin.support-tickets.edit', $record)"
    :fields="[
        'Ticket #'         => $record->ticket_number,
        'Customer'         => $customer ? trim($customer->first_name.' '.$customer->last_name) : null,
        'Assigned to'      => $agent?->name,
        'Priority'         => ucfirst($record->priority ?? ''),
        'Status'           => display_label($record->status, 'ticket_status'),
        'Category'         => $record->category,
        'Resolved at'      => optional($record->resolved_at)->format('Y-m-d H:i'),
        'Description'      => ['value' => $record->description, 'wide' => true],
        'Resolution notes' => ['value' => $record->resolution_notes, 'wide' => true],
        'Created'          => $record->created_at?->format('Y-m-d H:i'),
    ]" />

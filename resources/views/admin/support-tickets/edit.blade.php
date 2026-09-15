@php
    $form = compact(
        'customers',
        'agents',
        'agentCount',
        'canOverrideAssignment',
        'priorities',
        'statuses',
        'categories',
        'subjectsByCategory',
        'sources',
        'contactKinds',
        'ticketNumberPreview',
    );
@endphp
<x-admin.edit-page
    :title="'Edit ticket '.$record->ticket_number"
    heading="Edit ticket"
    :subheading="$record->ticket_number"
    :action="route('admin.support-tickets.update', $record)"
    :destroyAction="route('admin.support-tickets.destroy', $record)"
    :cancelUrl="route('admin.support-tickets.show', $record)"
    submitLabel="Save changes">
    @include('admin.support-tickets._form', ['record' => $record] + $form)
</x-admin.edit-page>

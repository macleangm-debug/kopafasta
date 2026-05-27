<x-admin.edit-page
    :title="'Edit ticket '.$record->ticket_number"
    heading="Edit ticket"
    :subheading="$record->ticket_number"
    :action="route('admin.support-tickets.update', $record)"
    :destroyAction="route('admin.support-tickets.destroy', $record)"
    :cancelUrl="route('admin.support-tickets.show', $record)"
    submitLabel="Save changes">
    @include('admin.support-tickets._form')
</x-admin.edit-page>

<x-admin.edit-page
    :title="'Edit complaint '.$record->complaint_number"
    heading="Edit complaint"
    :subheading="$record->complaint_number"
    :action="route('admin.complaints.update', $record)"
    :destroyAction="route('admin.complaints.destroy', $record)"
    :cancelUrl="route('admin.complaints.show', $record)"
    submitLabel="Save changes">
    @include('admin.complaints._form')
</x-admin.edit-page>

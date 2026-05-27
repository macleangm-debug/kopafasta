<x-admin.edit-page
    :title="'Edit '.$record->application_number"
    heading="Edit application"
    :subheading="$record->application_number"
    :action="route('admin.loan-applications.update', $record)"
    :destroyAction="route('admin.loan-applications.destroy', $record)"
    :cancelUrl="route('admin.loan-applications.show', $record)"
    submitLabel="Save changes">
    @include('admin.loan-applications._form')
</x-admin.edit-page>

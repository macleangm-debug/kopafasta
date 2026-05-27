<x-admin.edit-page
    :title="'Edit '.trim($record->first_name.' '.$record->last_name)"
    heading="Edit guarantor"
    :subheading="$record->phone"
    :action="route('admin.guarantors.update', $record)"
    :destroyAction="route('admin.guarantors.destroy', $record)"
    :cancelUrl="route('admin.guarantors.show', $record)"
    submitLabel="Save changes">
    @include('admin.guarantors._form')
</x-admin.edit-page>

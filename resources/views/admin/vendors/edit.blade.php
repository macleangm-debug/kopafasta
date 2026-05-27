<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit vendor"
    :subheading="$record->vendor_number"
    :action="route('admin.vendors.update', $record)"
    :destroyAction="route('admin.vendors.destroy', $record)"
    :cancelUrl="route('admin.vendors.show', $record)"
    submitLabel="Save changes">
    @include('admin.vendors._form')
</x-admin.edit-page>

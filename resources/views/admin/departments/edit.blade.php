<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit department" :subheading="$record->code"
    :action="route('admin.departments.update', $record)"
    :destroyAction="route('admin.departments.destroy', $record)"
    :cancelUrl="route('admin.departments.show', $record)"
    submitLabel="Save changes">
    @include('admin.departments._form')
</x-admin.edit-page>

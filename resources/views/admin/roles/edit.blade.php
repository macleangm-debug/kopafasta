<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit role" :subheading="$record->code"
    :action="route('admin.roles.update', $record)"
    :destroyAction="route('admin.roles.destroy', $record)"
    :cancelUrl="route('admin.roles.show', $record)"
    submitLabel="Save changes">
    @include('admin.roles._form')
</x-admin.edit-page>

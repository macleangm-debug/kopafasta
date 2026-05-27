<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit branch"
    :subheading="$record->code"
    :action="route('admin.branches.update', $record)"
    :destroyAction="route('admin.branches.destroy', $record)"
    :cancelUrl="route('admin.branches.show', $record)"
    submitLabel="Save changes">
    @include('admin.branches._form')
</x-admin.edit-page>

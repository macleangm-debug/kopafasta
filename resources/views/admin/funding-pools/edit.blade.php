<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit funding pool"
    :subheading="$record->name"
    :action="route('admin.funding-pools.update', $record)"
    :destroyAction="route('admin.funding-pools.destroy', $record)"
    :cancelUrl="route('admin.funding-pools.show', $record)"
    submitLabel="Save changes">
    @include('admin.funding-pools._form')
</x-admin.edit-page>

<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit fee" :subheading="$record->code"
    :action="route('admin.charges-fees.update', $record)"
    :destroyAction="route('admin.charges-fees.destroy', $record)"
    :cancelUrl="route('admin.charges-fees.show', $record)"
    submitLabel="Save changes">
    @include('admin.charges-fees._form')
</x-admin.edit-page>

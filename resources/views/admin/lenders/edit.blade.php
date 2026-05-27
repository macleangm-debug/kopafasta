<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit lender"
    :subheading="$record->code"
    :action="route('admin.lenders.update', $record)"
    :destroyAction="route('admin.lenders.destroy', $record)"
    :cancelUrl="route('admin.lenders.show', $record)"
    submitLabel="Save changes">
    @include('admin.lenders._form')
</x-admin.edit-page>

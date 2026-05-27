<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit disbursement method" :subheading="$record->code"
    :action="route('admin.disbursement-methods.update', $record)"
    :destroyAction="route('admin.disbursement-methods.destroy', $record)"
    :cancelUrl="route('admin.disbursement-methods.show', $record)"
    submitLabel="Save changes">
    @include('admin.disbursement-methods._form')
</x-admin.edit-page>

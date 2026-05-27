<x-admin.edit-page
    :title="'Edit KYC #'.$record->id"
    heading="Edit KYC record"
    :subheading="'#'.$record->id"
    :action="route('admin.customer-kycs.update', $record)"
    :destroyAction="route('admin.customer-kycs.destroy', $record)"
    :cancelUrl="route('admin.customer-kycs.show', $record)"
    submitLabel="Save changes">
    @include('admin.customer-kycs._form')
</x-admin.edit-page>

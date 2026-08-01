<x-admin.edit-page
    :title="'Edit '.$record->name"
    heading="Edit loan product"
    :subheading="$record->code"
    :action="route('admin.loan-products.update', $record)"
    :destroyAction="route('admin.loan-products.destroy', $record)"
    :cancelUrl="route('admin.loan-products.show', $record)"
    submitLabel="Save changes"
    enctype="multipart/form-data">
    @include('admin.loan-products._form')
</x-admin.edit-page>

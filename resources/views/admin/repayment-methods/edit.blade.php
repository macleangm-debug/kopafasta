<x-admin.edit-page
    :title="'Edit '.$record->name" heading="Edit repayment method" :subheading="$record->code"
    :action="route('admin.repayment-methods.update', $record)"
    :destroyAction="route('admin.repayment-methods.destroy', $record)"
    :cancelUrl="route('admin.repayment-methods.show', $record)"
    submitLabel="Save changes">
    @include('admin.repayment-methods._form')
</x-admin.edit-page>

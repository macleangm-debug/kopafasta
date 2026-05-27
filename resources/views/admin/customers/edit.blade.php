<x-admin.edit-page
    :title="'Edit '.trim($record->first_name.' '.$record->last_name)"
    heading="Edit customer"
    :subheading="$record->customer_number"
    :action="route('admin.customers.update', $record)"
    :destroyAction="route('admin.customers.destroy', $record)"
    :cancelUrl="route('admin.customers.show', $record)"
    submitLabel="Save changes">
    @include('admin.customers._form')
</x-admin.edit-page>

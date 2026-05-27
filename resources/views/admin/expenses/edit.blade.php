<x-admin.edit-page
    :title="'Edit expense '.$record->reference"
    heading="Edit expense"
    :subheading="$record->category"
    :action="route('admin.expenses.update', $record)"
    :destroyAction="route('admin.expenses.destroy', $record)"
    :cancelUrl="route('admin.expenses.show', $record)"
    submitLabel="Save changes">
    @include('admin.expenses._form')
</x-admin.edit-page>

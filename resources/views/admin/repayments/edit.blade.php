<x-admin.edit-page
    :title="'Edit repayment '.$record->reference"
    heading="Edit repayment"
    :subheading="$record->reference"
    :action="route('admin.repayments.update', $record)"
    :destroyAction="route('admin.repayments.destroy', $record)"
    :cancelUrl="route('admin.repayments.show', $record)"
    submitLabel="Save changes">
    @include('admin.repayments._form')
</x-admin.edit-page>

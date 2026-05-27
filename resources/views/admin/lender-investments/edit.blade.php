<x-admin.edit-page
    :title="'Edit '.$record->reference"
    heading="Edit investment"
    :subheading="$record->reference"
    :action="route('admin.lender-investments.update', $record)"
    :destroyAction="route('admin.lender-investments.destroy', $record)"
    :cancelUrl="route('admin.lender-investments.show', $record)"
    submitLabel="Save changes">
    @include('admin.lender-investments._form')
</x-admin.edit-page>

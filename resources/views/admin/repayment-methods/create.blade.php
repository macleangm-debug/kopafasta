<x-admin.create-page
    title="New repayment method" heading="New repayment method" subheading="How customers can repay"
    :action="route('admin.repayment-methods.store')" :cancelUrl="route('admin.repayment-methods.index')"
    submitLabel="Create method">
    @include('admin.repayment-methods._form', ['record' => null])
</x-admin.create-page>

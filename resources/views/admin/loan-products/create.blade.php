<x-admin.create-page
    title="New loan product"
    heading="New loan product"
    subheading="Define a credit product with pricing & limits"
    :action="route('admin.loan-products.store')"
    :cancelUrl="route('admin.loan-products.index')"
    submitLabel="Create product">
    @include('admin.loan-products._form', ['record' => null])
</x-admin.create-page>

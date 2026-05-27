<x-admin.create-page
    title="New repayment"
    heading="New repayment"
    subheading="Record a loan payment"
    :action="route('admin.repayments.store')"
    :cancelUrl="route('admin.repayments.index')"
    submitLabel="Create repayment">
    @include('admin.repayments._form', ['record' => null])
</x-admin.create-page>

<x-admin.create-page
    title="New risk rule" heading="New risk scoring rule" subheading="Define a scoring factor"
    :action="route('admin.risk-scoring-rules.store')" :cancelUrl="route('admin.risk-scoring-rules.index')"
    submitLabel="Create rule">
    @include('admin.risk-scoring-rules._form', ['record' => null])
</x-admin.create-page>

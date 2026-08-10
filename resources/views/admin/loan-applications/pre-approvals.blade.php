<x-admin.layout title="Credit Committee" heading="Credit Committee" subheading="Applications awaiting committee decision after analyst recommendation">
@include('admin.loan-applications._pipeline-tabs', ['active' => 'committee'])

    <div class="mb-4 rounded-xl bg-gradient-to-r from-amber-50 to-white ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-950">
        <p class="font-semibold">Committee queue</p>
        <p class="mt-1 text-amber-900/80">These applications have a credit recommendation and need pre-approval or a counter-offer from the credit committee team.</p>
    </div>

    @livewire('admin.loan-applications-table', ['pipeline' => 'committee', 'lockStage' => true])
</x-admin.layout>

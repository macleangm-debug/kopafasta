<x-admin.layout title="Loan Restructuring" heading="Loan Restructuring" subheading="Loans under modification or restructuring">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @livewire('admin.loans-table', ['status' => 'restructuring', 'lockStatus' => true])
</x-admin.layout>

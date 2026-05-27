<x-admin.layout title="Closed Loans" heading="Closed Loans" subheading="Fully repaid or written off">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @livewire('admin.loans-table', ['status' => 'closed', 'lockStatus' => true])
</x-admin.layout>

<x-admin.layout title="Active Loans" heading="Active loans" subheading="Disbursed and currently being repaid">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @include('admin.loans._toolbar')

    @livewire('admin.loans-table', ['status' => 'active', 'lockStatus' => true])
</x-admin.layout>

<x-admin.layout title="Loans in Arrears" heading="Loans in Arrears" subheading="Accounts with overdue installments flagged by the daily arrears job">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">
            {{ session('status') }}
        </div>
    @endif

    @livewire('admin.loans-table', ['status' => 'arrears', 'lockStatus' => true])
</x-admin.layout>

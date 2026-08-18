<x-admin.layout title="Closed Loans" heading="" subheading="">
    <x-admin.letterhead kicker="Loan book" title="Closed loans" subtitle="Fully repaid or written off" />
@livewire('admin.loans-table', ['status' => 'closed', 'lockStatus' => true])
</x-admin.layout>

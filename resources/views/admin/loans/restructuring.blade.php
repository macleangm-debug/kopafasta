<x-admin.layout title="Loan Restructuring" heading="" subheading="">
    <x-admin.letterhead kicker="Credit management" title="Loan restructuring" subtitle="Loans under modification or restructuring" />
@livewire('admin.loans-table', ['status' => 'restructuring', 'lockStatus' => true])
</x-admin.layout>

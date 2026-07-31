<x-admin.layout title="Loan allocations" heading="Loan allocations" subheading="Capital deployed from funding pools into loans (auto-created when loans are funded)">
    <div class="mb-4 rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-3 text-sm text-gray-700">
        These records are normally created by the funding allocator — not entered by hand. Use
        <a href="{{ route('admin.capital-funding.index') }}" class="font-semibold text-brand hover:underline">Capital funding</a>
        for the dashboard and interest share under
        <a href="{{ route('admin.settings.finance') }}" class="font-semibold text-brand hover:underline">Settings → Finance</a>.
    </div>
    @livewire('admin.lender-investments-table')
</x-admin.layout>

<x-admin.layout title="Capital Partners" heading="" subheading="">
    <x-admin.letterhead kicker="Capital" title="Capital partners" subtitle="Institutional and individual capital providers" />
    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <a href="{{ route('admin.capital-funding.index') }}" class="font-semibold text-brand hover:underline">← {{ __('admin.capital_funding.title') }}</a>
    </div>
    <x-admin.index-toolbar route="admin.lenders" label="New capital partner" />
    @livewire('admin.lenders-table')
</x-admin.layout>

<x-admin.layout title="Credit committee" heading="Credit committee" subheading="Final credit decisions — approve, counter-offer, or reject">

    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Pre-approval queue</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['pre_approval'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-brand/20 bg-brand-muted/20 p-5">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">With recommendation</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['awaiting_decision'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.loan-applications.pre-approvals') }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">
            Open committee queue
        </a>
        <a href="{{ route('admin.credit-team.index') }}"
           class="inline-flex text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            Team roster →
        </a>
    </div>

</x-admin.layout>

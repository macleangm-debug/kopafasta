<x-admin.layout title="Credit management" heading="Credit management team" subheading="Post-approval through disbursement — offer, fees, contract, capital, payout">

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Post-approval</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['approved'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-gray-200 p-5">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Ready / disbursement stage</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['disbursement_stage'] }}</p>
        </div>
        <div class="rounded-xl bg-white ring-1 ring-brand/20 bg-brand-muted/20 p-5">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Pending loans</p>
            <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['pending_loans'] }}</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.loan-applications.pipeline.approved') }}"
           class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">
            Post-approval queue
        </a>
        <a href="{{ route('admin.loan-applications.pipeline.disbursement') }}"
           class="inline-flex bg-white ring-1 ring-brand/20 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">
            Disbursement pipeline
        </a>
        <a href="{{ route('admin.loans.disbursement') }}"
           class="inline-flex bg-white ring-1 ring-brand/20 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl">
            Disbursement queue
        </a>
        <a href="{{ route('admin.credit-team.index') }}"
           class="inline-flex text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            Team roster →
        </a>
    </div>

</x-admin.layout>

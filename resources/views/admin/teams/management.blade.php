<x-admin.layout title="Credit management" heading="" subheading="">

    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Management home</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Own the facility after committee approval — offer, fees, destination, contract, then release and payout.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-4 gap-4">
                <div class="rounded-xl bg-brand-gold/20 ring-1 ring-brand-gold/40 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Management approval</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['awaiting_management'] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 mt-1">Committee done · matrix requires you</p>
                </div>
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Management queue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['approved'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Offer / fees / destination / contract</p>
                </div>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Release queue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['disbursement_stage'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Ready for release pipeline</p>
                </div>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Payout queue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['pending_loans'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Loan records awaiting payout</p>
                </div>
            </div>
        </div>
    </section>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6">
        <p class="text-xs font-semibold uppercase tracking-widest text-brand mb-3">Queues</p>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('admin.loan-applications.pipeline.management-approval') }}"
               class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
                Management approval
            </a>
            <a href="{{ route('admin.loan-applications.pipeline.approved') }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-brand-muted/40 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl ring-1 ring-brand/20">
                Management queue
            </a>
            <a href="{{ route('admin.loan-applications.pipeline.disbursement') }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-brand-muted/40 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl ring-1 ring-brand/20">
                Release queue
            </a>
            <a href="{{ route('admin.loans.disbursement') }}"
               class="inline-flex items-center gap-2 bg-white hover:bg-brand-muted/40 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl ring-1 ring-brand/20">
                Payout queue
            </a>
            <a href="{{ route('admin.credit-team.index') }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
                Credit teams →
            </a>
        </div>
        <p class="text-xs text-gray-500 mt-4">
            Spine: <span class="font-semibold text-gray-700">Offer → Fees → Destination → Contract → Release → Payout → Active loan</span>.
            Screening and committee stay on their desks. Rejected files do not come here.
        </p>
    </div>

</x-admin.layout>

<x-admin.layout title="Credit committee" heading="" subheading="">

    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Credit committee</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Validate screening or record a different decision — approve, counter-offer, or reject. Capital and funding source are decided here.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-3 gap-4">
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Pre-approval queue</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['pre_approval'] }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">With recommendation</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['awaiting_decision'] }}</p>
                </div>
                <div class="rounded-xl bg-rose-50 ring-1 ring-rose-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-rose-800 font-semibold">System sorted</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['system_sorted'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">Capacity auto-reject · 12h window</p>
                </div>
            </div>
        </div>
    </section>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.loan-applications.pre-approvals') }}"
           class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
            Open committee queue
        </a>
        <a href="{{ route('admin.loan-applications.pipeline.system-sorted') }}"
           class="inline-flex items-center gap-2 bg-white hover:bg-brand-muted/40 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl ring-1 ring-brand/20">
            System sorted
        </a>
        <a href="{{ route('admin.credit-team.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            Team roster →
        </a>
    </div>

</x-admin.layout>

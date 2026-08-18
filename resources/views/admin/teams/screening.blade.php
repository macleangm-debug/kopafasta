<x-admin.layout title="Credit screening" heading="" subheading="">

    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Credit desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Credit screening team</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Documents, face/ID, affordability, and the screening decision — then push to committee.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-3 gap-4">
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Screening</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['screening'] }}</p>
                </div>
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Credit appraisal</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['appraisal'] }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Assigned to me</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ $counts['mine'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.loan-applications.pipeline.under-review') }}"
           class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
            Open screening queue
        </a>
        <a href="{{ route('admin.loan-applications.rejected') }}"
           class="inline-flex items-center gap-2 bg-white hover:bg-brand-muted/40 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl ring-1 ring-brand/20">
            Rejected files
        </a>
        <a href="{{ route('admin.credit-team.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            Team roster →
        </a>
    </div>

</x-admin.layout>

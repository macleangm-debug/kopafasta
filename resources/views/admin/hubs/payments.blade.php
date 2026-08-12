<x-admin.layout title="Payments" heading="" subheading="">

    <section class="mb-8">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">Collections desk</p>
                <h1 class="text-2xl sm:text-3xl font-bold mt-1">Payments</h1>
                <p class="text-sm text-white/75 mt-2 max-w-2xl">
                    Borrower payments, membership renewals, and repayments — with ledger health at a glance.
                </p>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Pending verify</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['pending_verify']) }}</p>
                </div>
                <div class="rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-emerald-800 font-semibold">Verified today</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['verified_today']) }}</p>
                </div>
                <div class="rounded-xl bg-sky-50 ring-1 ring-sky-100 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-sky-800 font-semibold">Repayments due</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['repayments_due']) }}</p>
                </div>
                <div class="rounded-xl bg-brand-muted/50 ring-1 ring-brand/10 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Membership pending</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['membership_pending']) }}</p>
                </div>
                <div class="rounded-xl {{ $counts['missing_journal'] > 0 ? 'bg-rose-50 ring-rose-100' : 'bg-gray-50 ring-gray-100' }} ring-1 px-4 py-4">
                    <p class="text-[10px] uppercase tracking-widest {{ $counts['missing_journal'] > 0 ? 'text-rose-800' : 'text-gray-600' }} font-semibold">Missing journal</p>
                    <p class="text-3xl font-bold text-gray-900 mt-2 tabular-nums">{{ number_format($counts['missing_journal']) }}</p>
                </div>
            </div>
        </div>
    </section>

    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.payments.ledger') }}"
           class="inline-flex items-center gap-2 bg-brand-gold hover:brightness-95 text-brand font-bold text-sm px-5 py-2.5 rounded-xl shadow-sm ring-1 ring-brand/15">
            Payments ledger
        </a>
        <a href="{{ route('admin.payments.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900 px-4 py-2.5 rounded-xl ring-1 ring-gray-200 bg-gray-50">
            Verify payments
        </a>
        <a href="{{ route('admin.repayments.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900 px-4 py-2.5 rounded-xl ring-1 ring-gray-200 bg-gray-50">
            Loan repayments
        </a>
        <a href="{{ route('admin.membership-payments.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900 px-4 py-2.5 rounded-xl ring-1 ring-gray-200 bg-gray-50">
            Membership &amp; renewals
        </a>
        <a href="{{ route('admin.journal-entries.index') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-gray-600 hover:text-gray-900 px-4 py-2.5">
            Journal entries →
        </a>
    </div>

</x-admin.layout>

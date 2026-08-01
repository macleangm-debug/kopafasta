<x-admin.layout title="Risk Scoring Rules" heading="Risk Scoring Rules" subheading="Custom rule library — live underwriting score uses the factors below">
    <div class="mb-6 rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-5 py-4">
        <p class="text-sm font-semibold text-brand">Live application risk score (used on every loan review)</p>
        <p class="text-xs text-gray-600 mt-1">Starts at 100. These deductions are what currently drive the score on the application review page — not the free-text rules below.</p>
        <ul class="mt-3 grid sm:grid-cols-2 gap-2 text-xs text-gray-800">
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Profile below threshold → −15 (−5 more if under 90%)</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">NIDA not verified → −20</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Face pending / rejected → −10 / −25</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Affordability fail / warn → −30 / −12</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Required documents incomplete → −10</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Guarantor required & not approved → −12</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Overdue instalments → −8 each (max −25)</li>
            <li class="rounded-lg bg-white/80 ring-1 ring-brand/10 px-3 py-2">Bands: ≥75 Approve · ≥50 Refer · else Reject</li>
        </ul>
        <p class="text-xs text-gray-500 mt-3">Eligible loan amount is separate: income × multiplier, repayment history, membership, trust/referral boosts, and profile completion (<code>LoanQualificationService</code>).</p>
    </div>

    <x-admin.index-toolbar route="admin.risk-scoring-rules" label="New experimental rule" />
    <p class="text-xs text-amber-800 bg-amber-50 ring-1 ring-amber-200 rounded-lg px-3 py-2 mb-4">
        Experimental rules below are not yet applied to live scoring. Prefer adjusting the live factors above (or product/loan settings) until the rule engine is wired in.
    </p>
    @livewire('admin.risk-scoring-rules-table')
</x-admin.layout>

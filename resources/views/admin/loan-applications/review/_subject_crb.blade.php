@php
    $customer = $review['customer'];
    $isGuarantor = (bool) ($review['is_guarantor_subject'] ?? false);
    $crb = $isGuarantor
        ? ($review['guarantor_row']['crb'] ?? [])
        : ($review['crb'] ?? []);
    $explain = $isGuarantor
        ? ($review['guarantor_row']['crb_explanation'] ?? app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb))
        : app(\App\Services\CrbCreditCheckService::class)->recommendationExplanation($crb);
    $rec = strtolower((string) ($crb['recommendation'] ?? ''));
    $history = collect($crb['loan_history'] ?? []);
    $externalLoans = (int) ($crb['existing_loans'] ?? 0);
    $outstanding = (float) ($crb['outstanding_balance'] ?? 0);
@endphp

<section class="rounded-2xl ring-1 ring-brand/10 bg-white overflow-hidden">
    <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/40 to-white flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ $isGuarantor ? 'Guarantor' : 'Borrower' }} · CRB</p>
            <h2 class="text-sm font-semibold text-gray-900 mt-0.5">Credit bureau report</h2>
            <p class="text-xs text-gray-500 mt-0.5">Freshness-driven — reused within the configured window.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="inline-flex text-xs font-bold rounded-full px-3 py-1 bg-brand-muted text-brand ring-1 ring-brand/15 uppercase">
                {{ $rec !== '' ? $rec : '—' }}
            </span>
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1 bg-gray-100 text-gray-700">
                Score {{ $crb['score'] ?? '—' }}
            </span>
        </div>
    </div>

    <div class="p-5 space-y-5">
        <p class="text-sm text-gray-700">{{ $explain['summary'] ?? 'No CRB explanation available.' }}</p>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-amber-800 font-semibold">Other institutions</p>
                <p class="text-xl font-bold text-amber-950 mt-1">{{ $externalLoans }}</p>
                <p class="text-[11px] text-amber-900/80 mt-0.5">Active loans on CRB</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Outstanding</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ format_money($outstanding) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Delinquencies</p>
                <p class="text-xl font-bold text-gray-900 mt-1">{{ $crb['delinquencies'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Freshness</p>
                <p class="text-sm font-bold text-gray-900 mt-1">{{ $crb['freshness_label'] ?? '—' }}</p>
            </div>
        </div>

        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500 mb-2">Loans at other institutions</p>
            @if ($history->isEmpty() && $externalLoans === 0)
                <p class="text-sm text-gray-500">No external loans reported on this CRB pull.</p>
            @elseif ($history->isEmpty())
                <p class="text-sm text-gray-700">
                    CRB reports <span class="font-semibold">{{ $externalLoans }}</span> existing loan(s)
                    @if ($outstanding > 0)
                        with outstanding {{ format_money($outstanding) }}
                    @endif
                    — detailed lender lines not provided by bureau.
                </p>
            @else
                <ul class="divide-y divide-gray-100 rounded-xl ring-1 ring-gray-200 overflow-hidden bg-white">
                    @foreach ($history as $row)
                        <li class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $row['lender'] ?? $row['institution'] ?? 'Other lender' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 capitalize">{{ $row['status'] ?? '—' }}</p>
                            </div>
                            <p class="font-semibold text-gray-900">{{ format_money((float) ($row['balance'] ?? $row['outstanding'] ?? 0)) }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>

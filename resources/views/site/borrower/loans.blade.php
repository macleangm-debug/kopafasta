<x-site.borrower-layout title="My loans — Kopafasta" active="loans">

    <h1 class="text-2xl font-bold mb-1">My loans</h1>
    <p class="text-sm text-gray-500 mb-8">Loans you have borrowed and loans you guarantee for other members.</p>

    <section class="mb-10">
        <h2 class="text-lg font-semibold mb-4">Loans borrowed</h2>
        @if ($loans->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
                <p class="text-gray-500">No loans yet. Once an application is approved and disbursed, it will appear here.</p>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($loans as $loan)
                    @php
                        $paid = max(0, $loan->principal_amount - $loan->outstanding_balance);
                        $pct = $loan->principal_amount > 0 ? min(100, ($paid / $loan->principal_amount) * 100) : 0;
                        $statusBadge = match ($loan->status) {
                            'active','disbursed' => 'bg-emerald-100 text-emerald-700',
                            'arrears'            => 'bg-red-100 text-red-700',
                            'closed'             => 'bg-gray-100 text-gray-700',
                            default              => 'bg-amber-100 text-amber-700',
                        };
                        $monthly = $loan->tenure_months > 0 ? round(($loan->principal_amount / $loan->tenure_months) + ($loan->principal_amount * $loan->interest_rate)) : 0;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
                            <div>
                                <p class="text-xs text-gray-500">{{ $loan->product->name ?? '—' }}</p>
                                <p class="font-mono font-bold text-lg">{{ $loan->loan_number }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 {{ $statusBadge }}">{{ ucfirst($loan->status) }}</span>
                        </div>

                        <div class="grid sm:grid-cols-4 gap-4 mb-5">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Loan amount</p>
                                <p class="font-semibold text-sm">TZS {{ number_format($loan->principal_amount) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Outstanding</p>
                                <p class="font-semibold text-sm">TZS {{ number_format($loan->outstanding_balance) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Monthly</p>
                                <p class="font-semibold text-sm">TZS {{ number_format($monthly) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Rate · tenure</p>
                                <p class="font-semibold text-sm">{{ number_format($loan->interest_rate * 100, 2) }}% / mo · {{ $loan->tenure_months }} mo</p>
                            </div>
                        </div>

                        <div class="mb-5">
                            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                <span>Repaid {{ number_format($pct, 0) }}%</span>
                                <span>Matures {{ $loan->maturity_date ? \Carbon\Carbon::parse($loan->maturity_date)->format('d M Y') : '—' }}</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('site.borrower.schedule', $loan->id) }}" class="bg-gray-900 hover:bg-gray-800 text-white text-xs font-semibold px-4 py-2 rounded-full">View repayment schedule</a>
                            <a href="{{ route('site.borrower.payments') }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 text-xs font-semibold px-4 py-2 rounded-full">Make a payment</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section>
        <h2 class="text-lg font-semibold mb-4">Loans guaranteed</h2>
        @if (($guaranteedLinks ?? collect())->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center">
                <p class="text-gray-500">You are not guaranteeing any loans yet.</p>
                <a href="{{ route('site.borrower.guarantor-requests') }}" class="inline-flex mt-4 text-sm font-semibold text-amber-700 hover:underline">View guarantor requests →</a>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($guaranteedLinks as $link)
                    @php
                        $app = $link->application;
                        $borrower = $app?->customer;
                        $loan = $app?->loan;
                    @endphp
                    <div class="bg-white rounded-2xl border border-gray-200 p-6">
                        <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                            <div>
                                <p class="text-xs text-gray-500">{{ $app?->product?->name ?? 'Loan application' }}</p>
                                <p class="font-semibold">{{ $borrower?->full_name ?? 'Borrower' }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $app?->application_number }}</p>
                            </div>
                            <span class="text-xs font-semibold rounded-full px-2.5 py-1 bg-sky-100 text-sky-800">Guarantor</span>
                        </div>
                        <div class="grid sm:grid-cols-3 gap-4 text-sm">
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Requested</p>
                                <p class="font-semibold">TZS {{ number_format($app?->requested_amount ?? 0) }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Application status</p>
                                <p class="font-semibold capitalize">{{ str_replace('_', ' ', $app?->status ?? '—') }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-widest text-gray-400">Loan status</p>
                                <p class="font-semibold capitalize">{{ $loan ? ucfirst($loan->status) : 'Not disbursed' }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</x-site.borrower-layout>

<x-admin.layout :title="'Refund '.$borrowerRefund->reference" :heading="'Refund '.$borrowerRefund->reference" subheading="Borrower auction surplus payout">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="mb-4">
        <a href="{{ route('admin.borrower-refunds.index') }}" class="text-sm font-semibold text-amber-700 hover:underline">← All refunds</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-xl ring-1 ring-gray-200 p-6">
            <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                <div><dt class="text-xs text-gray-500 uppercase">Borrower</dt><dd class="font-semibold">{{ $borrowerRefund->customer?->first_name }} {{ $borrowerRefund->customer?->last_name }}</dd></div>
                <div><dt class="text-xs text-gray-500 uppercase">Loan</dt><dd class="font-mono">{{ $borrowerRefund->loan?->loan_number ?? '—' }}</dd></div>
                <div><dt class="text-xs text-gray-500 uppercase">Amount</dt><dd class="font-bold text-lg">{{ format_money($borrowerRefund->amount) }}</dd></div>
                <div><dt class="text-xs text-gray-500 uppercase">Status</dt><dd class="capitalize font-semibold">{{ str_replace('_', ' ', $borrowerRefund->status) }}</dd></div>
                @if ($borrowerRefund->settlement)
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500 uppercase">Auction proceeds</dt><dd>{{ format_money($borrowerRefund->settlement->auction_proceeds) }}</dd></div>
                @endif
            </dl>

            @if ($borrowerRefund->payout_channel)
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h3 class="text-sm font-semibold mb-3">Payout details</h3>
                    <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                        <div><dt class="text-xs text-gray-500">Channel</dt><dd class="capitalize">{{ str_replace('_', ' ', $borrowerRefund->payout_channel) }}</dd></div>
                        @if ($borrowerRefund->payout_phone)<div><dt class="text-xs text-gray-500">Phone</dt><dd>{{ $borrowerRefund->payout_phone }}</dd></div>@endif
                        @if ($borrowerRefund->payout_provider)<div><dt class="text-xs text-gray-500">Provider</dt><dd>{{ $borrowerRefund->payout_provider }}</dd></div>@endif
                        @if ($borrowerRefund->payout_account_name)<div><dt class="text-xs text-gray-500">Account name</dt><dd>{{ $borrowerRefund->payout_account_name }}</dd></div>@endif
                        @if ($borrowerRefund->payout_account_number)<div><dt class="text-xs text-gray-500">Account number</dt><dd class="font-mono">{{ $borrowerRefund->payout_account_number }}</dd></div>@endif
                    </dl>
                </div>
            @endif

            @if ($borrowerRefund->accrualJournalEntry || $borrowerRefund->payoutJournalEntry)
                <div class="mt-6 pt-6 border-t border-gray-100">
                    <h3 class="text-sm font-semibold mb-3">General ledger</h3>
                    <ul class="text-sm space-y-2">
                        @if ($borrowerRefund->accrualJournalEntry)
                            <li>
                                Accrual
                                <a href="{{ route('admin.journal-entries.show', $borrowerRefund->accrualJournalEntry) }}" class="font-mono text-amber-700 hover:underline">{{ $borrowerRefund->accrualJournalEntry->entry_number }}</a>
                                · {{ $borrowerRefund->accrual_posted_at?->format('d M Y') }}
                            </li>
                        @endif
                        @if ($borrowerRefund->payoutJournalEntry)
                            <li>
                                Payout
                                <a href="{{ route('admin.journal-entries.show', $borrowerRefund->payoutJournalEntry) }}" class="font-mono text-amber-700 hover:underline">{{ $borrowerRefund->payoutJournalEntry->entry_number }}</a>
                                · {{ $borrowerRefund->payout_posted_at?->format('d M Y') }}
                            </li>
                        @endif
                    </ul>
                </div>
            @endif

            @if ($borrowerRefund->status === 'paid')
                <div class="mt-6 pt-6 border-t border-gray-100 text-sm space-y-1">
                    <p>Paid {{ $borrowerRefund->paid_at?->format('d M Y H:i') }} · Ref {{ $borrowerRefund->payment_reference }}</p>
                    @if ($borrowerRefund->disbursement_status === 'dispatched')
                        <p class="text-gray-600">Mobile money auto-disburse · {{ $borrowerRefund->disbursementMobileMoneyAccount?->name ?? 'System' }}</p>
                    @endif
                </div>
            @endif
        </div>

        @if ($borrowerRefund->isPayable())
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h3 class="text-sm font-semibold mb-4">Mark as paid</h3>
                <form method="POST" action="{{ route('admin.borrower-refunds.pay', $borrowerRefund) }}" class="space-y-3">
                    @csrf
                    @if ($borrowerRefund->payout_channel === 'mobile_money')
                        <label class="flex items-start gap-2 text-sm bg-amber-50 ring-1 ring-amber-100 rounded-lg px-3 py-2.5">
                            <input type="hidden" name="auto_disburse" value="0">
                            <input type="checkbox" name="auto_disburse" value="1" class="mt-0.5 rounded border-gray-300 text-amber-600" @checked(old('auto_disburse'))>
                            <span>
                                <span class="font-semibold text-gray-900">Send via mobile money</span>
                                <span class="block text-xs text-gray-600 mt-0.5">
                                    @if ($disbursementDummy)
                                        Dummy gateway — instant test payout with auto reference.
                                    @else
                                        Requires disbursement account with API credentials, or pay manually below.
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endif
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Payment reference</label>
                        <input type="text" name="payment_reference" value="{{ old('payment_reference') }}" class="w-full rounded-lg border-gray-300 text-sm" placeholder="M-Pesa / bank ref (optional if auto-disburse)">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm px-4 py-2 rounded-lg">
                        Confirm payout
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-admin.layout>

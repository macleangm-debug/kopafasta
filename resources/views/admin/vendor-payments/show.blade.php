<x-admin.show-page
    :title="$payment->invoice_number"
    :heading="$payment->invoice_number"
    :subheading="$payment->vendor?->name ?? 'Partner payout'"
    :backUrl="route('admin.payments.ledger', ['direction' => 'out', 'tab' => 'partners'])">

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">What this payout is for</p>
                        <h2 class="text-lg font-bold text-gray-900 mt-1">{{ $payment->description ?: ucfirst(str_replace('_', ' ', (string) $payment->source_type)) }}</h2>
                    </div>
                    <span class="text-xs font-semibold rounded-full px-3 py-1 {{ match ($payment->status) {
                        'paid' => 'bg-emerald-100 text-emerald-800',
                        'approved' => 'bg-sky-100 text-sky-800',
                        'pending' => 'bg-amber-100 text-amber-900',
                        default => 'bg-gray-100 text-gray-600',
                    } }}">{{ strtoupper((string) $payment->status) }}</span>
                </div>

                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Amount</dt>
                        <dd class="font-semibold tabular-nums">{{ format_money((float) $payment->amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Source</dt>
                        <dd>{{ str_replace('_', ' ', (string) $payment->source_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Partner</dt>
                        <dd>
                            @if ($payment->vendor)
                                <a href="{{ route('admin.partners.show', $payment->vendor) }}" class="font-semibold text-brand hover:underline">{{ $payment->vendor->name }}</a>
                                <span class="text-xs text-gray-500">{{ $payment->vendor->vendor_number }}</span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Invoice</dt>
                        <dd class="font-mono">{{ $payment->invoice_number }}</dd>
                    </div>
                </dl>

                @if ($application)
                    <div class="rounded-xl bg-brand-muted/40 ring-1 ring-brand/15 px-4 py-3 text-sm">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-brand">Linked loan / application</p>
                        <p class="mt-1">
                            <a href="{{ route('admin.loan-applications.show', $application) }}" class="font-semibold text-brand hover:underline">
                                {{ $application->application_number }}
                            </a>
                            @if ($application->product)
                                · {{ $application->product->name }}
                                @if ($application->product->code)
                                    <span class="font-mono text-xs text-gray-500">({{ $application->product->code }})</span>
                                @endif
                            @endif
                        </p>
                        <p class="text-xs text-gray-600 mt-1">
                            {{ $application->customer?->full_name }}
                            · Requested {{ format_money((float) $application->requested_amount) }}
                        </p>
                    </div>
                @endif

                @if ($payment->task)
                    <div class="rounded-xl bg-slate-50 ring-1 ring-slate-200 px-4 py-3 text-sm">
                        <p class="text-[10px] uppercase tracking-widest font-semibold text-slate-600">Partner task</p>
                        <p class="mt-1 font-semibold">{{ ucfirst(str_replace('_', ' ', (string) $payment->task->task_type)) }} · {{ ucfirst(str_replace('_', ' ', (string) $payment->task->status)) }}</p>
                        @if ($payment->task->customer_name)
                            <p class="text-xs text-gray-600">{{ $payment->task->customer_name }}</p>
                        @endif
                        @if ($payment->task->due_at)
                            <p class="text-xs text-gray-500">Due {{ $payment->task->due_at->format('d M Y') }}</p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-3">
                <h3 class="text-sm font-semibold text-gray-900">How this hits the ledger</h3>
                @if ($inboundAccrued)
                    <p class="text-sm text-gray-600">
                        When the borrower paid this fee, cash already came in and we credited <strong>partner payable</strong> for the partner share (markup stayed as platform revenue). Approving here does not book another expense. Recording the payout is money out: debit partner payable, credit cash/bank.
                    </p>
                @else
                    <p class="text-sm text-gray-600">
                        Recording this payout is money out: debit the partner payable (or supplier payable) and credit cash/bank. Financial reports pick this up from that journal, not from the Approve click alone.
                    </p>
                @endif
                @if ($journal)
                    <p class="text-xs font-semibold uppercase tracking-widest text-emerald-800">Posted journal {{ $journal->entry_number }}</p>
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr><th class="py-1 text-left">Account</th><th class="py-1 text-right">Debit</th><th class="py-1 text-right">Credit</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($journal->lines as $line)
                                <tr>
                                    <td class="py-1.5">{{ $line->account?->name ?? $line->description }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $line->debit > 0 ? format_money((float) $line->debit) : '—' }}</td>
                                    <td class="py-1.5 text-right tabular-nums">{{ $line->credit > 0 ? format_money((float) $line->credit) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm text-amber-800">No cash journal yet. Record the payout below to post it.</p>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            @if (in_array($payment->status, ['pending', 'approved'], true))
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Record payout</h3>
                    <p class="text-xs text-gray-500">This marks the line PAID on the partner file and posts cash out. Use the bank or mobile-money reference from the transfer.</p>
                    <form method="POST" action="{{ route('admin.partner-payments.pay', $payment) }}" class="space-y-3">
                        @csrf
                        <x-admin.input name="channel" label="Channel" placeholder="Bank / M-Pesa / Tigo" :value="old('channel', $payment->channel)" />
                        <x-admin.input name="reference" label="Payment reference" placeholder="Bank slip or PSP id" :value="old('reference', $payment->reference)" />
                        <label class="block text-xs font-semibold text-gray-700">Notes</label>
                        <textarea name="notes" rows="2" class="w-full rounded-xl border-gray-300 text-sm">{{ old('notes') }}</textarea>
                        <button type="submit" class="w-full inline-flex justify-center text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2.5 rounded-xl">
                            {{ $payment->status === 'pending' ? 'Approve and record as PAID' : 'Record as PAID' }}
                        </button>
                    </form>
                    @if ($payment->status === 'pending')
                        <form method="POST" action="{{ route('admin.partner-payments.approve', $payment) }}">
                            @csrf
                            <button type="submit" class="w-full text-sm font-semibold text-sky-800 bg-sky-50 hover:bg-sky-100 ring-1 ring-sky-200 px-4 py-2 rounded-xl">
                                Approve only (pay later)
                            </button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('admin.partner-payments.cancel', $payment) }}">
                        @csrf
                        <button type="submit" class="w-full text-sm font-semibold text-red-700 hover:underline">Cancel payout</button>
                    </form>
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 text-sm text-gray-600">
                    @if ($payment->status === 'paid')
                        Paid {{ $payment->paid_at?->format('d M Y H:i') }}.
                        @if ($payment->channel) Channel {{ $payment->channel }}. @endif
                        @if ($payment->reference) Ref {{ $payment->reference }}. @endif
                    @else
                        This payout is {{ $payment->status }}.
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-admin.show-page>

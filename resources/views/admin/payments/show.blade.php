<x-admin.layout
    :title="'Payment '.$payment->reference"
    :heading="$payment->reference"
    subheading="Who paid, what it was for, related loan, and ledger entry">

<div class="mb-4">
        <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-brand hover:text-brand-light">← Back to payments</a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Payment details</h2>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 uppercase">Who paid</dt>
                        <dd class="font-medium">
                            {{ trim(($payment->customer->first_name ?? '').' '.($payment->customer->last_name ?? '')) }}
                            <span class="text-gray-500 font-normal">· {{ $payment->customer->phone ?? '—' }}</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Payment type</dt>
                        <dd>{{ $payment->typeLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">What it was for</dt>
                        <dd>{{ $payment->typeLabel() }}@if ($payment->loan) · Loan {{ $payment->loan->loan_number ?? $payment->loan->id }}@endif</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Related loan</dt>
                        <dd>
                            @if ($payment->loan)
                                <a href="{{ route('admin.loans.show', $payment->loan) }}" class="text-brand hover:text-brand-light font-mono text-xs">{{ $payment->loan->loan_number ?? $payment->loan->id }}</a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Method</dt>
                        <dd>{{ $payment->methodLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Amount</dt>
                        <dd class="font-semibold">{{ format_money($payment->amount) }}</dd>
                    </div>
                    @php
                        $feeSplit = (array) data_get($payment->provider_meta, 'fee_split', []);
                        $ins = (array) data_get($payment->provider_meta, 'collateral_insurance', []);
                        if (array_key_exists('gps_partner_share', $feeSplit) || array_key_exists('other_partner_share', $feeSplit)) {
                            $partnerShare = (float) ($feeSplit['gps_partner_share'] ?? 0) + (float) ($feeSplit['other_partner_share'] ?? 0);
                            $markupAmount = (float) ($feeSplit['gps_markup'] ?? 0) + (float) ($feeSplit['other_markup'] ?? 0);
                        } else {
                            $partnerShare = (float) ($feeSplit['partner_share'] ?? $ins['base_premium'] ?? $ins['partner_share'] ?? 0);
                            $markupAmount = (float) ($feeSplit['markup_amount'] ?? $ins['markup_amount'] ?? 0);
                        }
                    @endphp
                    @if ($partnerShare > 0 || $markupAmount > 0)
                        <div>
                            <dt class="text-xs text-gray-500 uppercase">Partner share</dt>
                            <dd>{{ format_money($partnerShare) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500 uppercase">Platform markup</dt>
                            <dd>{{ format_money($markupAmount) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Verification status</dt>
                        <dd>{{ $payment->statusLabel() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Payment date</dt>
                        <dd>{{ $payment->payment_date?->format('d M Y') ?? $payment->created_at?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    @if ($payment->mobile_number)
                        <div>
                            <dt class="text-xs text-gray-500 uppercase">Mobile number</dt>
                            <dd class="font-mono">{{ $payment->mobile_number }}</dd>
                        </div>
                    @endif
                    @if ($payment->bankAccount)
                        <div class="col-span-2">
                            <dt class="text-xs text-gray-500 uppercase mb-1">Expected bank account</dt>
                            <dd class="text-sm">
                                {{ $payment->bankAccount->bank_name }} · {{ $payment->bankAccount->name }}<br>
                                <span class="font-mono">{{ $payment->bankAccount->account_number }}</span>
                            </dd>
                        </div>
                    @endif
                    @if ($payment->journalEntry)
                        <div class="col-span-2">
                            <dt class="text-xs text-gray-500 uppercase">Journal entry</dt>
                            <dd>
                                <a href="{{ route('admin.journal-entries.show', $payment->journalEntry) }}" class="text-brand hover:text-brand-light font-mono text-xs">
                                    {{ $payment->journalEntry->entry_number }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($payment->verification_notes)
                    <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700 whitespace-pre-line">{{ $payment->verification_notes }}</div>
                @endif
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Proof submitted</h2>
                @if ($payment->hasProof())
                    <p class="text-sm text-gray-600 mb-3">{{ $payment->proof_original_name ?? 'Uploaded document' }}</p>
                    <a href="{{ route('admin.payments.proof', $payment) }}"
                       class="inline-flex items-center rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-light">
                        View proof
                    </a>
                @else
                    <p class="text-sm text-gray-500">No proof uploaded yet.</p>
                @endif
            </div>

            @if (! empty($fundDestinations))
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                    <h2 class="text-sm font-semibold text-gray-900 mb-1">Where funds go</h2>
                    <p class="text-xs text-gray-500 mb-4">Allocation after verification — microfinance, capital partners, suppliers, affiliates.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500 border-b border-gray-100">
                                    <th class="py-2 pr-3">Party</th>
                                    <th class="py-2 pr-3">Role</th>
                                    <th class="py-2 pr-3 text-right">Amount</th>
                                    <th class="py-2 pr-3">Status</th>
                                    <th class="py-2">Detail</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($fundDestinations as $row)
                                    <tr>
                                        <td class="py-2.5 pr-3 font-medium text-gray-900">
                                            @if (! empty($row['url']))
                                                <a href="{{ $row['url'] }}" class="text-brand hover:underline">{{ $row['party'] }}</a>
                                            @else
                                                {{ $row['party'] }}
                                            @endif
                                        </td>
                                        <td class="py-2.5 pr-3 text-gray-600">{{ str_replace('_', ' ', $row['role']) }}</td>
                                        <td class="py-2.5 pr-3 text-right tabular-nums font-semibold">{{ format_money($row['amount']) }}</td>
                                        <td class="py-2.5 pr-3 text-gray-600">{{ $row['status'] }}</td>
                                        <td class="py-2.5 text-gray-500 text-xs">{{ $row['detail'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        @if ($payment->isPending())
            <div class="space-y-4">
                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Verify payment</h3>
                    <p class="text-xs text-gray-500 mb-4">Match bank account, amount, reference and payment date before approving.</p>
                    <form method="POST" action="{{ route('admin.payments.verify', $payment) }}"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('admin.confirm.payment_verify_title')),
                              message: @js(__('admin.confirm.payment_verify_message')),
                              confirmLabel: @js(__('admin.confirm.payment_verify_confirm')),
                              confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                              tone: 'confirm',
                          })">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                            Verify payment
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Request clarification</h3>
                    <form method="POST" action="{{ route('admin.payments.clarify', $payment) }}" class="space-y-2">
                        @csrf
                        <textarea name="notes" required rows="3" placeholder="What does the borrower need to clarify?"
                                  class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                        <button type="submit" class="w-full rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                            Request clarification
                        </button>
                    </form>
                </div>

                <div class="bg-white rounded-xl ring-1 ring-gray-200 p-5">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">Reject payment</h3>
                    <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="space-y-2"
                          @submit.prevent="window.confirmForm($el, {
                              title: @js(__('admin.confirm.payment_reject_title')),
                              message: @js(__('admin.confirm.payment_reject_message')),
                              confirmLabel: @js(__('admin.confirm.payment_reject_confirm')),
                              confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                              tone: 'warning',
                          })">
                        @csrf
                        <textarea name="notes" rows="3" placeholder="Rejection reason"
                                  class="w-full rounded-lg border-gray-200 text-sm"></textarea>
                        <button type="submit" class="w-full rounded-lg bg-white px-4 py-2 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                            Reject payment
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-admin.layout>

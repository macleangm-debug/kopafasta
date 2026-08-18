@php
    $customer = $payment->customer;
    $payerName = $customer
        ? (trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: '—')
        : '—';
    $bank = $payment->bankAccount;
    $mm = $payment->mobileMoneyAccount;
    $destinationLabel = $bank
        ? trim(($bank->bank_name ?? '').' · '.($bank->name ?? ''))
        : ($mm ? trim(($mm->provider ?? 'Mobile money').' · '.($mm->name ?? '')) : null);
    $destinationNumber = $bank?->account_number
        ?? $mm?->msisdn
        ?? $mm?->paybill_number
        ?? $mm?->till_number
        ?? $payment->mobile_number;
    $ctx = $paymentContext ?? $payment->adminContext();
    $isBankQueue = $payment->payment_method === 'bank_transfer'
        && in_array($payment->status, ['pending_verification', 'clarification_requested', 'awaiting_payment'], true);
@endphp

<x-admin.layout
    :title="'Payment '.$payment->reference"
    heading=""
    subheading="">

    <div class="mb-4">
        <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-brand hover:text-brand-light">← Back to payments</a>
    </div>

    <section class="mb-6">
        <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-6 py-7 text-white">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-4 min-w-0">
                        <div class="shrink-0 rounded-xl bg-white/10 ring-1 ring-white/20 p-2.5">
                            <x-site.brand-mark size="sm" variant="light" />
                        </div>
                        <div>
                        <p class="text-[10px] uppercase tracking-[0.2em] font-semibold text-brand-gold">
                            {{ brand_name() }} · {{ $isBankQueue ? 'Bank matching desk' : 'Payment detail' }}
                        </p>
                        <h1 class="text-2xl sm:text-3xl font-bold mt-1 font-mono tracking-tight">{{ $payment->reference }}</h1>
                        <p class="text-sm text-white/75 mt-2 max-w-xl">
                            @if ($isBankQueue)
                                Confirm the transfer landed in the expected account, then verify to post the ledger entry.
                            @else
                                Completed mobile payments are confirmed by the PSP. Context below links this payment to the loan / product / partner.
                            @endif
                        </p>
                    </div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] uppercase tracking-widest text-white/60 font-semibold">Amount</p>
                        <p class="text-3xl font-bold tabular-nums mt-1">{{ format_money($payment->amount) }}</p>
                        <span class="inline-flex mt-2 text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 bg-white/15">
                            {{ $payment->statusLabel() }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="bg-white px-6 py-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Who paid</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $payerName }}</p>
                    <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $customer?->phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Payment type</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $ctx['type'] }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $payment->methodLabel() }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Product / loan</p>
                    @if ($ctx['product'])
                        <p class="font-semibold text-gray-900 mt-1">{{ $ctx['product'] }}</p>
                        @if ($ctx['product_code'])
                            <p class="text-xs text-gray-500 mt-0.5 font-mono">{{ $ctx['product_code'] }}</p>
                        @endif
                    @else
                        <p class="font-semibold text-gray-400 mt-1">—</p>
                    @endif
                    @if ($ctx['application_number'])
                        <p class="text-xs mt-1">
                            Application
                            @if ($ctx['application_url'])
                                <a href="{{ $ctx['application_url'] }}" class="font-semibold text-brand hover:underline">{{ $ctx['application_number'] }}</a>
                            @else
                                <span class="font-mono">{{ $ctx['application_number'] }}</span>
                            @endif
                        </p>
                    @endif
                    @if ($ctx['loan_number'])
                        <p class="text-xs mt-0.5">
                            Loan
                            @if ($ctx['loan_url'])
                                <a href="{{ $ctx['loan_url'] }}" class="font-semibold text-brand hover:underline">{{ $ctx['loan_number'] }}</a>
                            @else
                                <span class="font-mono">{{ $ctx['loan_number'] }}</span>
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Ledger</p>
                    @if ($payment->journalEntry)
                        <a href="{{ route('admin.journal-entries.show', $payment->journalEntry) }}"
                           class="font-mono text-sm font-semibold text-brand mt-1 inline-block">
                            {{ $payment->journalEntry->entry_number }}
                        </a>
                    @else
                        <p class="font-semibold text-amber-800 mt-1">Not posted yet</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $isBankQueue ? 'Posts on verify' : 'Check posting job' }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @if ($ctx['asset'] || $ctx['partner'] || $ctx['application_number'] || $ctx['loan_number'] || $ctx['product'])
        <section class="mb-6 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand/10 bg-gradient-to-r from-brand-muted/50 to-white">
                <p class="text-[10px] uppercase tracking-[0.18em] text-brand font-bold">Linked context</p>
                <h2 class="text-sm font-bold text-gray-900 mt-0.5">What this payment belongs to</h2>
            </div>
            <div class="p-5 grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Type</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $ctx['type'] }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Product</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $ctx['product'] ?: '—' }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Application / loan</p>
                    <p class="font-semibold text-gray-900 mt-1">
                        {{ $ctx['application_number'] ?: ($ctx['loan_number'] ?: '—') }}
                    </p>
                </div>
                <div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-4 py-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Partner / asset</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $ctx['partner'] ?: '—' }}</p>
                    @if ($ctx['asset'])
                        <p class="text-xs text-gray-500 mt-0.5">{{ $ctx['asset'] }}</p>
                    @endif
                    @if ($ctx['partner_role'])
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $ctx['partner_role'] }}</p>
                    @endif
                </div>
            </div>
        </section>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            @if ($isBankQueue || $payment->payment_method === 'bank_transfer')
            <div class="rounded-2xl overflow-hidden ring-1 ring-emerald-200/80 shadow-sm bg-gradient-to-br from-emerald-50 to-white">
                <div class="px-6 py-5 border-b border-emerald-100/80">
                    <p class="text-[10px] uppercase tracking-[0.18em] font-semibold text-emerald-800">Expected destination</p>
                    <h2 class="text-lg font-bold text-gray-900 mt-1">Match this account in your bank</h2>
                    <p class="text-sm text-gray-600 mt-1">Find the incoming transfer for this amount, then confirm below.</p>
                </div>
                <div class="px-6 py-5 grid sm:grid-cols-2 gap-5">
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Account</p>
                        <p class="font-semibold text-gray-900 mt-1">{{ $destinationLabel ?: 'Destination not set on payment' }}</p>
                        @if ($destinationNumber)
                            <p class="font-mono text-sm text-gray-800 mt-2 tracking-wide">{{ $destinationNumber }}</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Look for</p>
                        <ul class="mt-2 space-y-1.5 text-sm text-gray-700">
                            <li class="flex gap-2"><span class="text-emerald-700 font-bold">·</span> Amount {{ format_money($payment->amount) }}</li>
                            <li class="flex gap-2"><span class="text-emerald-700 font-bold">·</span> Date around {{ format_app_datetime($payment->adminOccurredAt()) }}</li>
                            <li class="flex gap-2"><span class="text-emerald-700 font-bold">·</span> Payer {{ $payerName }}@if($payment->mobile_number) ({{ $payment->mobile_number }})@endif</li>
                            <li class="flex gap-2"><span class="text-emerald-700 font-bold">·</span> Ref {{ $payment->reference }}</li>
                        </ul>
                    </div>
                </div>
                @if ($payment->payment_instructions)
                    <div class="px-6 pb-5">
                        <div class="rounded-xl bg-white/80 ring-1 ring-emerald-100 px-4 py-3 text-sm text-gray-700 whitespace-pre-line">{{ $payment->payment_instructions }}</div>
                    </div>
                @endif
            </div>
            @else
            <div class="rounded-2xl overflow-hidden ring-1 ring-brand/15 shadow-sm bg-gradient-to-br from-brand-muted/40 to-white px-6 py-5">
                <p class="text-[10px] uppercase tracking-[0.18em] font-semibold text-brand">Mobile money · PSP confirmed</p>
                <h2 class="text-lg font-bold text-gray-900 mt-1">Collection channel</h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $destinationLabel ?: 'Mobile money' }}
                    @if ($destinationNumber)
                        · <span class="font-mono">{{ $destinationNumber }}</span>
                    @endif
                </p>
                @if ($payment->provider_ref)
                    <p class="text-xs text-gray-500 mt-2 font-mono">Provider ref · {{ $payment->provider_ref }}</p>
                @endif
            </div>
            @endif

            <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-6">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">Proof submitted</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Receipt or bank slip from the borrower</p>
                    </div>
                    @if ($payment->hasProof())
                        <span class="text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100">Uploaded</span>
                    @else
                        <span class="text-[10px] font-bold uppercase tracking-wider rounded-full px-2.5 py-1 bg-amber-50 text-amber-900 ring-1 ring-amber-100">
                            {{ $payment->payment_method === 'mobile_money' ? 'API / no slip' : 'Missing' }}
                        </span>
                    @endif
                </div>
                @if ($payment->hasProof())
                    <p class="text-sm text-gray-600 mb-3">{{ $payment->proof_original_name ?? 'Uploaded document' }}</p>
                    <a href="{{ route('admin.payments.proof', $payment) }}"
                       class="inline-flex items-center rounded-xl bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-light shadow-sm">
                        View proof
                    </a>
                @else
                    <p class="text-sm text-gray-500">
                        @if ($payment->payment_method === 'mobile_money')
                            Mobile payments are confirmed by the PSP API — a bank slip is not required.
                        @else
                            No proof uploaded yet — you can still verify if the bank statement clearly matches.
                        @endif
                    </p>
                @endif
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
            @if ($partnerShare > 0 || $markupAmount > 0 || ! empty($fundDestinations))
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-6">
                    <h2 class="text-sm font-bold text-gray-900 mb-1">Where funds go</h2>
                    <p class="text-xs text-gray-500 mb-4">Allocation / partner share</p>
                    @if ($partnerShare > 0 || $markupAmount > 0)
                        <dl class="grid sm:grid-cols-2 gap-3 text-sm mb-4">
                            <div class="rounded-xl bg-gray-50 px-4 py-3">
                                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Partner share</dt>
                                <dd class="font-semibold mt-1">{{ format_money($partnerShare) }}</dd>
                            </div>
                            <div class="rounded-xl bg-gray-50 px-4 py-3">
                                <dt class="text-[10px] uppercase tracking-widest text-gray-500">Platform markup</dt>
                                <dd class="font-semibold mt-1">{{ format_money($markupAmount) }}</dd>
                            </div>
                        </dl>
                    @endif
                    @if (! empty($fundDestinations))
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
                    @endif
                </div>
            @endif

            @if ($payment->verification_notes)
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-5 text-sm text-gray-700 whitespace-pre-line">{{ $payment->verification_notes }}</div>
            @endif
        </div>

        <div class="space-y-4">
            @if ($payment->isPending() && $isBankQueue)
                <div class="rounded-2xl overflow-hidden ring-1 ring-emerald-200 shadow-sm bg-white">
                    <div class="bg-gradient-to-br from-emerald-600 to-emerald-800 px-5 py-4 text-white">
                        <p class="text-[10px] uppercase tracking-widest text-emerald-100 font-semibold">Confirm match</p>
                        <h3 class="text-base font-bold mt-1">Verify payment</h3>
                        <p class="text-xs text-emerald-50/90 mt-1.5 leading-relaxed">
                            Only verify after you see this amount on the expected bank statement.
                        </p>
                    </div>
                    <div class="p-5">
                        <ul class="text-xs text-gray-600 space-y-2 mb-4">
                            <li class="flex gap-2"><span class="text-emerald-600">✓</span> Destination account matches</li>
                            <li class="flex gap-2"><span class="text-emerald-600">✓</span> Amount {{ format_money($payment->amount) }}</li>
                            <li class="flex gap-2"><span class="text-emerald-600">✓</span> Date / payer / reference checked</li>
                        </ul>
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}"
                              @submit.prevent="window.confirmForm($el, {
                                  title: @js(__('admin.confirm.payment_verify_title')),
                                  message: @js(__('admin.confirm.payment_verify_message')),
                                  confirmLabel: @js(__('admin.confirm.payment_verify_confirm')),
                                  confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white',
                                  tone: 'confirm',
                              })">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm">
                                Verify &amp; post ledger
                            </button>
                        </form>
                    </div>
                </div>

                <div class="rounded-2xl bg-white ring-1 ring-sky-100 shadow-sm p-5">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">Request clarification</h3>
                    <p class="text-xs text-gray-500 mb-3">Ask the borrower for a clearer slip or correct reference.</p>
                    <form method="POST" action="{{ route('admin.payments.clarify', $payment) }}" class="space-y-2">
                        @csrf
                        <textarea name="notes" required rows="3" placeholder="What does the borrower need to clarify?"
                                  class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                        <button type="submit" class="w-full rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700">
                            Request clarification
                        </button>
                    </form>
                </div>

                <div class="rounded-2xl bg-white ring-1 ring-rose-100 shadow-sm p-5">
                    <h3 class="text-sm font-bold text-gray-900 mb-1">Reject payment</h3>
                    <p class="text-xs text-gray-500 mb-3">Use when the bank transfer cannot be matched.</p>
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
                                  class="w-full rounded-xl border-gray-200 text-sm"></textarea>
                        <button type="submit" class="w-full rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">
                            Reject payment
                        </button>
                    </form>
                </div>
            @else
                <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Status</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ $payment->statusLabel() }}</p>
                    <p class="text-sm text-gray-500 mt-2">
                        @if ($payment->payment_method === 'mobile_money' && $payment->isVerified())
                            Mobile payment confirmed by the PSP — no bank verification step.
                        @else
                            This payment is no longer awaiting bank verification.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-admin.layout>

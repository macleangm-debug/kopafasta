<x-site.borrower-layout :title="brand_title('Offer — '.$application->application_number)" active="loans">

    <div class="max-w-3xl mx-auto">
        <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:underline">&larr; Back to application</a>

        @if (session('status'))
            <div class="mt-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('otp_sent'))
            <div class="mt-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('otp_sent') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-3 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-900 to-amber-900 text-white px-6 py-4 flex items-center justify-between gap-3">
                <x-site.brand-mark size="sm" variant="light" />
                <span class="text-xs uppercase tracking-widest text-white/80">Offer summary</span>
            </div>
            <div class="p-6">
            <h1 class="text-xl font-bold text-gray-900">View offer</h1>
            <p class="text-sm text-gray-600 mt-1">Commercial offer summary — not a legal contract. Application <span class="font-mono">{{ $application->application_number }}</span></p>

            @if (! $agreement)
                <div class="mt-6 rounded-lg bg-gray-50 ring-1 ring-gray-200 p-5 text-sm text-gray-700">
                    Your offer has not been issued yet. Once your application is approved, the credit team will issue a formal offer here for your acceptance.
                </div>
            @else
                @php
                    $snap = $agreement->snapshot ?? [];
                @endphp

                <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div><div class="text-xs uppercase text-gray-500">Reference</div><div class="font-mono text-gray-900">{{ $agreement->reference }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Status</div>
                        <span @class([
                            'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
                            'bg-emerald-100 text-emerald-800' => $agreement->status === 'signed',
                            'bg-amber-100 text-amber-800'     => $agreement->status === 'sent',
                            'bg-gray-100 text-gray-700'       => in_array($agreement->status, ['draft','expired','cancelled']),
                        ])>{{ $agreement->status === 'signed' ? 'Accepted' : ucfirst($agreement->status) }}</span>
                    </div>
                    <div><div class="text-xs uppercase text-gray-500">Approved amount</div><div class="text-gray-900 font-semibold">{{ format_money($snap['principal'] ?? 0) }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Interest rate</div><div class="text-gray-900">{{ format_number(($snap['interest_rate'] ?? 0) * 100, 2) }}% / month</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Tenure</div><div class="text-gray-900">{{ $snap['tenure_months'] ?? '—' }} months</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Instalment amount</div><div class="text-gray-900">{{ format_money($snap['estimated_emi'] ?? 0) }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Repayment frequency</div><div class="text-gray-900">{{ ucfirst($snap['repayment_cadence'] ?? 'weekly') }}</div></div>
                    <div><div class="text-xs uppercase text-gray-500">Number of instalments</div><div class="text-gray-900">{{ $snap['installment_count'] ?? count($snap['repayment_schedule'] ?? []) }}</div></div>
                    <div class="sm:col-span-2"><div class="text-xs uppercase text-gray-500">Total repayable</div><div class="text-gray-900 font-semibold">{{ format_money($snap['total_repayable'] ?? 0) }}</div></div>
                    @if ($agreement->expires_at)
                        <div class="sm:col-span-2"><div class="text-xs uppercase text-gray-500">Offer expires</div><div class="text-gray-900">{{ $agreement->expires_at->format('d M Y, H:i') }}</div></div>
                    @endif
                </div>

                <p class="mt-4 text-xs text-gray-500 rounded-lg bg-sky-50 ring-1 ring-sky-100 px-3 py-2">
                    Repayment dates are not shown because disbursement has not happened yet. Actual due dates will be set after the loan is funded.
                </p>

                @if ($agreement->isOfferExpired())
                    <div class="mt-5 rounded-lg bg-red-50 ring-1 ring-red-200 p-4 text-sm text-red-800">
                        This offer has expired. Contact the lender to request a new offer before you can accept.
                    </div>
                @endif

                @if (! $agreement->isSigned() && ! $agreement->isOfferExpired())
                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h2 class="text-sm font-semibold text-gray-900">Your decision</h2>
                        @if ($requireAcceptanceCode ?? false)
                            <p class="text-xs text-gray-600 mt-1">Confirm acceptance by entering the 6-digit code we send to your phone.</p>

                            <form method="POST" action="{{ route('site.borrower.application.agreement.otp', $application) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                                    @if ($agreement->otp_sent_at) Resend code @else Send code @endif
                                </button>
                            </form>

                            <form method="POST" action="{{ route('site.borrower.application.agreement.sign', $application) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                @csrf
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">6-digit code</label>
                                    <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required
                                           class="font-mono text-lg tracking-[0.4em] w-44 rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-lg">
                                    Accept offer
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-gray-600 mt-1">Review the offer summary above, then accept or decline.</p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <form method="POST" action="{{ route('site.borrower.application.agreement.accept', $application) }}"
                                      @submit.prevent="window.confirmForm($el, { title: 'Accept this offer?', message: 'You are accepting the loan terms shown in this offer summary.', confirmLabel: 'Accept offer', confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-lg">
                                        Accept offer
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('site.borrower.application.agreement.decline', $application) }}"
                                      @submit.prevent="window.confirmForm($el, { title: 'Reject this offer?', message: 'Your application will be withdrawn.', confirmLabel: 'Reject offer', confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-red-700 bg-red-50 hover:bg-red-100 px-5 py-2.5 rounded-lg">
                                        Reject offer
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="mt-6 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4 text-sm text-emerald-800">
                        <strong>{{ __('borrower.agreement.signed_on', ['date' => $agreement->signed_at->format('d M Y H:i')]) }}</strong>
                        <p class="mt-1">{{ __('borrower.agreement.signed_next_fees') }}</p>
                    </div>
                @endif
            @endif
            </div>
        </div>
    </div>

</x-site.borrower-layout>

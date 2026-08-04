<x-site.borrower-layout :title="brand_title(__('borrower.contract.page_title'))" active="loans" content-width="wide">

    <div>
        @php $repaymentCadences = __('borrower.agreement.repayment_cadences'); @endphp
        <a href="{{ route('site.borrower.application', $application) }}" class="text-sm text-amber-700 hover:underline">&larr; {{ __('borrower.contract.back') }}</a>

        @if (session('status'))
            <div class="mt-3 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif
        @if (session('otp_sent'))
            <div class="mt-3 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-800">{{ session('otp_sent') }}</div>
        @endif
        @if (session('error'))
            <div class="mt-3 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif

        {{-- Loan summary cards --}}
        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">{{ __('borrower.contract.loan_summary') }}</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.approved_amount') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ format_money($snap['principal'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.interest_rate') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ format_number(($snap['interest_rate'] ?? 0) * 100, 2) }}% / month</p>
                </div>
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.installment') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ format_money($snap['estimated_emi'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.repayment_frequency') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $repaymentCadences[$snap['repayment_cadence'] ?? 'weekly'] ?? ucfirst($snap['repayment_cadence'] ?? 'weekly') }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.total_repayable') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ format_money($snap['total_repayable'] ?? 0) }}</p>
                </div>
                <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-3">
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.guarantor') }}</p>
                    <p class="font-semibold text-gray-900 mt-1">{{ $guarantorName ?: '—' }}</p>
                </div>
            </div>
        </div>

        {{-- Repayment schedule --}}
        @if (! empty($scheduleRows))
            <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
                <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('borrower.contract.schedule_title') }}</h2>
                    @unless ($disbursed)
                        <span class="text-[10px] font-semibold uppercase tracking-widest text-amber-700">{{ __('borrower.contract.schedule_estimate') }}</span>
                    @endunless
                </div>
                @if ($disbursed)
                    <div class="overflow-x-auto -mx-1">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-[10px] uppercase tracking-widest text-gray-500 border-b border-gray-200">
                                    <th class="px-3 py-2">{{ __('borrower.contract.schedule_date') }}</th>
                                    <th class="px-3 py-2">{{ __('borrower.contract.schedule_installment') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('borrower.contract.schedule_amount') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($scheduleRows as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-700">{{ optional($row['due_date'])->format('d M Y') ?? '—' }}</td>
                                        <td class="px-3 py-2 font-medium text-gray-900">{{ __('borrower.contract.installment_n', ['n' => $row['installment_no']]) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ format_money($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex gap-3 overflow-x-auto pb-1 -mx-1 px-1">
                        @foreach ($scheduleRows as $row)
                            <div class="shrink-0 min-w-[9rem] rounded-xl bg-gray-50 ring-1 ring-gray-100 p-4 text-center">
                                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ __('borrower.contract.installment_n', ['n' => $row['installment_no']]) }}</p>
                                <p class="text-base font-bold text-gray-900 mt-2">{{ format_money($row['amount']) }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Progress checklist --}}
        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-900 mb-3">{{ __('borrower.contract.checklist.title') }}</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($checklist as $item)
                    @php
                        $statusLabel = match ($item['status']) {
                            'paid', 'accepted', 'complete', 'available' => '✓ '.__('borrower.contract.checklist.complete'),
                            'pending' => '⏳ '.__('borrower.contract.checklist.pending'),
                            'insufficient' => '✗ '.__('borrower.contract.checklist.insufficient'),
                            'locked' => '🔒 '.__('borrower.contract.checklist.locked'),
                            'not_generated' => '⏳ '.__('borrower.contract.checklist.generating'),
                            default => ucfirst(str_replace('_', ' ', $item['status'])),
                        };
                        $tone = ($item['complete'] ?? false) ? 'text-emerald-700' : 'text-gray-700';
                    @endphp
                    <li class="flex items-center justify-between gap-3 {{ $tone }}">
                        <span class="font-medium">{{ $item['label'] }}</span>
                        <span class="text-xs font-semibold">{{ $statusLabel }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Disbursement destination confirmation --}}
        @if (! empty($disbursementDetails['method'] ?? null))
            <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-5">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('borrower.disbursement_details.summary_title') }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('borrower.disbursement_details.summary_hint') }}</p>
                    </div>
                    <a href="{{ route('site.borrower.profile', ['section' => 'payment', 'edit' => 1, 'return' => route('site.borrower.application.contract', $application)]) }}"
                       class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                        {{ __('borrower.disbursement_details.change') }}
                    </a>
                </div>
                <dl class="mt-4 grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.disbursement_details.loan_amount') }}</dt>
                        <dd class="font-semibold text-gray-900 mt-1">{{ format_money($snap['principal'] ?? $application->requested_amount) }}</dd>
                    </div>
                    @foreach ($detailsService->displayLines($disbursementDetails) as $label => $value)
                        <div>
                            <dt class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ $label }}</dt>
                            <dd class="font-semibold text-gray-900 mt-1">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endif

        <div class="mt-4 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-gray-900 to-amber-900 text-white px-6 py-4 flex items-center justify-between gap-3">
                <x-site.brand-mark size="sm" variant="light" />
                <span class="text-xs uppercase tracking-widest text-white/80">{{ __('borrower.contract.page_title') }}</span>
            </div>
            <div class="p-6">
                <h1 class="text-xl font-bold text-gray-900">{{ __('borrower.contract.review_title') }}</h1>
                <p class="text-sm text-gray-600 mt-1">{{ __('borrower.contract.application_ref', ['ref' => $application->application_number]) }}</p>
                <p class="text-xs text-gray-500 mt-1 font-mono">{{ $contract->reference }}</p>

                <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mt-6 mb-3">{{ __('borrower.contract.parties') }}</h2>
                <div class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
                        <p class="text-[10px] uppercase text-gray-500">{{ __('borrower.contract.borrower') }}</p>
                        <p class="font-semibold mt-1">{{ $snap['customer_name'] ?? trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) }}</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4">
                        <p class="text-[10px] uppercase text-gray-500">{{ __('borrower.contract.company') }}</p>
                        <p class="font-semibold mt-1">{{ $snap['company_signatory'] ?? brand('legal_name') }}</p>
                    </div>
                    @if ($needsGuarantor || $guarantorName)
                        <div class="rounded-lg bg-gray-50 ring-1 ring-gray-100 p-4 sm:col-span-2">
                            <p class="text-[10px] uppercase text-gray-500">{{ __('borrower.contract.guarantor') }}</p>
                            <p class="font-semibold mt-1">{{ $guarantorName ?: '—' }}</p>
                            @if ($needsGuarantor)
                                <p class="text-xs mt-1 {{ $guarantorSigned ? 'text-emerald-700' : 'text-amber-700' }}">
                                    {{ $guarantorSigned ? __('borrower.contract.guarantor_signed') : __('borrower.contract.guarantor_pending') }}
                                </p>
                            @endif
                        </div>
                    @endif
                </div>

                <h2 class="text-xs font-semibold uppercase tracking-widest text-gray-500 mt-6 mb-3">{{ __('borrower.contract.signatures') }}</h2>
                <ul class="space-y-2 text-sm">
                    <li class="flex justify-between gap-3">
                        <span>{{ __('borrower.contract.borrower_signature') }}</span>
                        <span class="font-semibold {{ ($contract->isSigned() || $borrowerSignatureAvailable) ? 'text-emerald-700' : 'text-amber-700' }}">
                            @if ($contract->isSigned())
                                {{ __('borrower.contract.signed') }}
                            @elseif ($borrowerSignatureAvailable)
                                {{ __('borrower.contract.signature_available') }}
                            @else
                                {{ __('borrower.contract.signature_required') }}
                            @endif
                        </span>
                    </li>
                    @if ($needsGuarantor)
                        <li class="flex justify-between gap-3">
                            <span>{{ __('borrower.contract.guarantor_signature') }}</span>
                            <span class="font-semibold {{ $guarantorSigned ? 'text-emerald-700' : 'text-amber-700' }}">
                                {{ $guarantorSigned ? __('borrower.contract.signed') : __('borrower.contract.pending') }}
                            </span>
                        </li>
                    @endif
                    <li class="flex justify-between gap-3">
                        <span>{{ __('borrower.contract.company_signatory') }}</span>
                        <span class="font-semibold text-emerald-700">{{ __('borrower.contract.on_file') }}</span>
                    </li>
                    <li class="flex justify-between gap-3">
                        <span>{{ __('borrower.contract.company_stamp') }}</span>
                        <span class="font-semibold text-emerald-700">{{ __('borrower.contract.on_file') }}</span>
                    </li>
                </ul>

                <div class="mt-5 flex flex-wrap items-center gap-3">
                    <a href="{{ route('site.borrower.agreement.download', $contract) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-gray-900 hover:bg-gray-800 px-4 py-2 rounded-lg">
                        {{ __('borrower.contract.view_pdf') }}
                    </a>
                    <span @class([
                        'inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase',
                        'bg-emerald-100 text-emerald-800' => $contract->status === 'signed',
                        'bg-amber-100 text-amber-800'     => $contract->status === 'sent',
                        'bg-gray-100 text-gray-700'       => in_array($contract->status, ['draft','expired','cancelled']),
                    ])>{{ $contract->status === 'signed' ? __('borrower.contract.accepted') : __('borrower.contract.pending_signature') }}</span>
                </div>

                @if (! $contract->isSigned() && $contract->status !== 'cancelled')
                    <div class="mt-6 border-t border-gray-200 pt-5">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('borrower.contract.accept_title') }}</h2>
                        @if ($borrowerSignatureAvailable)
                            <p class="text-xs text-emerald-700 mt-1 font-medium">{{ __('borrower.contract.signature_reuse_help') }}</p>
                        @endif
                        @if ($requireAcceptanceCode ?? false)
                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.contract.accept_help') }}</p>

                            <form method="POST" action="{{ route('site.borrower.application.contract.otp', $application) }}" class="mt-3">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-amber-800 bg-amber-100 hover:bg-amber-200 px-4 py-2 rounded-lg">
                                    @if ($contract->otp_sent_at) {{ __('borrower.contract.resend_code') }} @else {{ __('borrower.contract.send_code') }} @endif
                                </button>
                                @if ($contract->otp_sent_at)
                                    <span class="ml-2 text-xs text-gray-500">{{ __('borrower.contract.last_sent', ['time' => $contract->otp_sent_at->diffForHumans()]) }}</span>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('site.borrower.application.contract.sign', $application) }}" class="mt-4 flex flex-wrap items-end gap-3"
                                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.contract.confirm_title')), message: @js(__('borrower.contract.confirm_message')), confirmLabel: @js(__('borrower.contract.accept_button')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
                                @csrf
                                <div>
                                    <label class="block text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('borrower.contract.otp_label') }}</label>
                                    <input type="text" name="otp" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required
                                           class="font-mono text-lg tracking-[0.4em] w-44 rounded-lg border-gray-300 focus:border-amber-500 focus:ring-amber-500">
                                </div>
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-lg">
                                    {{ __('borrower.contract.accept_button') }}
                                </button>
                                @error('otp') <p class="text-xs text-red-600 w-full">{{ $message }}</p> @enderror
                            </form>
                        @else
                            <p class="text-xs text-gray-600 mt-1">{{ __('borrower.contract.accept_direct_help') }}</p>
                            <form method="POST" action="{{ route('site.borrower.application.contract.accept', $application) }}" class="mt-4"
                                  @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.contract.confirm_title')), message: @js(__('borrower.contract.confirm_message')), confirmLabel: @js(__('borrower.contract.accept_button')), confirmClass: 'bg-emerald-600 hover:bg-emerald-700 text-white' })">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-5 py-2.5 rounded-lg">
                                    {{ __('borrower.contract.accept_button') }}
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('site.borrower.application.contract.decline', $application) }}" class="mt-4"
                              @submit.prevent="window.confirmForm($el, { title: @js(__('borrower.contract.decline_title')), message: @js(__('borrower.contract.decline_message')), confirmLabel: @js(__('borrower.contract.decline_button')), confirmClass: 'bg-red-600 hover:bg-red-700 text-white' })">
                            @csrf
                            <textarea name="reason" rows="2" placeholder="{{ __('borrower.contract.decline_reason') }}"
                                      class="w-full rounded-lg border-gray-300 text-sm mb-2"></textarea>
                            <button type="submit" class="inline-flex items-center gap-2 text-sm font-semibold text-red-700 bg-red-50 hover:bg-red-100 px-4 py-2 rounded-lg">
                                {{ __('borrower.contract.decline_button') }}
                            </button>
                        </form>
                    </div>
                @elseif ($contract->isSigned())
                    <div class="mt-6 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 p-4 text-sm text-emerald-800">
                        <strong>{{ __('borrower.contract.signed_on', ['date' => $contract->signed_at->format('d M Y H:i')]) }}</strong>
                        <p class="mt-1">{{ __('borrower.contract.ready_message') }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

</x-site.borrower-layout>

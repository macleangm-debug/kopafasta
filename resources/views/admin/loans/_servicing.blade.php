@php
    $servicingPanel = $servicingPanel ?? 'all';
    $showSummary = in_array($servicingPanel, ['all', 'summary'], true);
    $showFollowup = in_array($servicingPanel, ['all', 'followup'], true);
@endphp
@if ($servicing)
    @if ($showSummary)
    <div class="mt-4 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-700">Loan servicing</h3>
                <p class="text-xs text-gray-500 mt-0.5">Repayment progress — reminders for upcoming and overdue instalments</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($arrearCase ?? null)
                    <a href="{{ route('admin.arrear-cases.show', $arrearCase) }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg ring-1 ring-red-200">
                        Collection case #{{ $arrearCase->id }}
                    </a>
                @endif
                <a href="{{ route('admin.repayments.index') }}?loan={{ $loan->loan_number }}"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1.5 rounded-lg">
                    All repayments
                </a>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="rounded-lg bg-gray-50 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider text-gray-500">Repaid</p>
                <p class="text-lg font-bold text-gray-900">{{ format_number($servicing['progress_pct'], 1) }}%</p>
                <p class="text-xs text-gray-500">{{ format_money($servicing['principal_paid']) }} of {{ format_money($servicing['principal']) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider text-gray-500">Next installment</p>
                <p class="text-lg font-bold text-gray-900">
                    {{ $servicing['next_due_amount'] ? format_money($servicing['next_due_amount']) : '—' }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ $servicing['next_due_date'] ? $servicing['next_due_date']->format('d M Y') : 'No upcoming due date' }}
                </p>
            </div>
            <div class="rounded-lg {{ ($servicing['days_remaining'] ?? 0) < 0 ? 'bg-red-50' : 'bg-gray-50' }} px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider {{ ($servicing['days_remaining'] ?? 0) < 0 ? 'text-red-600' : 'text-gray-500' }}">Days to next due</p>
                <p class="text-lg font-bold {{ ($servicing['days_remaining'] ?? 0) < 0 ? 'text-red-800' : 'text-gray-900' }}">
                    @if (($servicing['days_remaining'] ?? null) === null)
                        —
                    @elseif ($servicing['days_remaining'] < 0)
                        {{ abs($servicing['days_remaining']) }} overdue
                    @else
                        {{ $servicing['days_remaining'] }} left
                    @endif
                </p>
            </div>
            <div class="rounded-lg {{ ($servicing['amount_in_arrears'] ?? 0) > 0 ? 'bg-red-50' : 'bg-emerald-50' }} px-3 py-2">
                <p class="text-[10px] uppercase tracking-wider {{ ($servicing['amount_in_arrears'] ?? 0) > 0 ? 'text-red-600' : 'text-emerald-700' }}">In arrears</p>
                <p class="text-lg font-bold {{ ($servicing['amount_in_arrears'] ?? 0) > 0 ? 'text-red-800' : 'text-emerald-900' }}">
                    {{ format_money($servicing['amount_in_arrears'] ?? 0) }}
                </p>
                @if (($servicing['overdue_installments'] ?? 0) > 0)
                    <p class="text-xs text-red-600">{{ $servicing['overdue_installments'] }} overdue installment(s)</p>
                @endif
            </div>
        </div>

        @if (! empty($activeRecovery))
            <x-admin.recovery-automation-status
                :level="$activeRecovery['level'] ?? null"
                :partner-name="$activeRecovery['partnerName'] ?? null"
                :sla-due-at="$activeRecovery['slaDueAt'] ?? null"
                :sla-days-left="$activeRecovery['slaDaysLeft'] ?? null"
                :status="$activeRecovery['status'] ?? null"
                :assignment-id="$activeRecovery['assignmentId'] ?? null"
                :arrear-case-id="$activeRecovery['arrearCaseId'] ?? null"
                :breached="(bool) ($activeRecovery['breached'] ?? false)"
            />
        @endif

        @if (! empty($auctionHold))
            <div class="mt-4">
                <x-site.auction-hold-banner :status="$auctionHold" />
            </div>
        @endif

        @if (! empty($collateralGps))
            <div class="mt-4">
                <x-collateral-gps-panel
                    :items="$collateralGps"
                    :installer-contact="$gpsInstallerContact ?? null"
                    :show-installer-contact="! empty($gpsInstallerContact)"
                    title="Collateral & GPS"
                />
            </div>
        @endif

        @if ($restructureRequests->isNotEmpty() || $topUpRequests->isNotEmpty())
            <div class="grid md:grid-cols-2 gap-4 mb-5">
                @if ($restructureRequests->isNotEmpty())
                    <div class="rounded-lg ring-1 ring-gray-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Restructure requests</p>
                        <ul class="space-y-2 text-sm">
                            @foreach ($restructureRequests as $request)
                                <li class="flex items-center justify-between gap-2">
                                    <a href="{{ route('admin.restructure-requests.show', $request) }}" class="text-brand hover:text-brand-light font-semibold">
                                        #{{ $request->id }}
                                    </a>
                                    <span class="text-xs capitalize text-gray-600">{{ $request->status }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($topUpRequests->isNotEmpty())
                    <div class="rounded-lg ring-1 ring-gray-200 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Top-up requests</p>
                        <ul class="space-y-2 text-sm">
                            @foreach ($topUpRequests as $request)
                                <li class="flex items-center justify-between gap-2">
                                    <a href="{{ route('admin.top-up-requests.show', $request) }}" class="text-brand hover:text-brand-light font-semibold">
                                        #{{ $request->id }}
                                    </a>
                                    <span class="text-xs capitalize text-gray-600">{{ $request->status }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

    </div>
    @endif

    @if ($showFollowup && in_array($loan->status, ['active', 'arrears', 'defaulted'], true))
    <div class="{{ $showSummary ? 'mt-4' : '' }} bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
            <div class="grid lg:grid-cols-2 gap-4">
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Log collection action</h4>
                    <form method="POST" action="{{ route('admin.loans.collection-actions', $loan) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label for="action_type" class="block text-xs font-medium text-gray-600 mb-1">Action type</label>
                            <select id="action_type" name="action_type" required
                                    class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="phone_call">Phone call</option>
                                <option value="sms_reminder">SMS reminder</option>
                                <option value="email_reminder">Email reminder</option>
                                <option value="field_visit">Field visit</option>
                                <option value="promise_to_pay">Promise to pay</option>
                                <option value="escalation">Escalation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="result" class="block text-xs font-medium text-gray-600 mb-1">Result</label>
                            <select id="result" name="result" class="w-full rounded-lg border-gray-300 text-sm">
                                <option value="">—</option>
                                <option value="contacted">Contacted</option>
                                <option value="no_answer">No answer</option>
                                <option value="wrong_number">Wrong number</option>
                                <option value="promised_payment">Promised payment</option>
                                <option value="dispute">Dispute</option>
                            </select>
                        </div>
                        <div>
                            <label for="notes" class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                            <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                      class="w-full rounded-lg border-gray-300 text-sm"
                                      placeholder="Summary of the follow-up"></textarea>
                        </div>
                        <button type="submit"
                                class="inline-flex items-center text-xs font-semibold text-white bg-brand hover:bg-brand-light px-3 py-2 rounded-lg">
                            Save action
                        </button>
                    </form>
                </div>

                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Recent collection actions</h4>
                    @if ($collectionActions->isEmpty())
                        <p class="text-sm text-gray-500">No collection actions logged yet.</p>
                    @else
                        <ul class="divide-y divide-gray-100 text-sm">
                            @foreach ($collectionActions as $action)
                                <li class="py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-gray-900">{{ str_replace('_', ' ', $action->action_type) }}</span>
                                        <span class="text-xs text-gray-500">{{ optional($action->performed_at)->format('d M Y H:i') }}</span>
                                    </div>
                                    @if ($action->result)
                                        <p class="text-xs text-gray-600 mt-0.5">Result: {{ str_replace('_', ' ', $action->result) }}</p>
                                    @endif
                                    @if ($action->notes)
                                        <p class="text-xs text-gray-500 mt-0.5">{{ $action->notes }}</p>
                                    @endif
                                    @if ($action->performer)
                                        <p class="text-[10px] text-gray-400 mt-0.5">{{ $action->performer->name }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
    </div>
    @endif

    @if ($showSummary && $recentRepayments->isNotEmpty())
    <div class="mt-4 bg-white rounded-2xl shadow-sm ring-1 ring-brand/10 p-6">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Recent repayments</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-2 pr-4">Reference</th>
                            <th class="text-left py-2 pr-4">Paid</th>
                            <th class="text-left py-2 pr-4">Channel</th>
                            <th class="text-right py-2 pr-4">Amount</th>
                            <th class="text-left py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($recentRepayments as $repayment)
                            <tr>
                                <td class="py-2 pr-4">
                                    <a href="{{ route('admin.repayments.show', $repayment) }}" class="font-mono text-xs text-brand hover:text-brand-light">
                                        {{ $repayment->reference }}
                                    </a>
                                </td>
                                <td class="py-2 pr-4 text-xs">{{ optional($repayment->paid_at)->format('d M Y') ?? '—' }}</td>
                                <td class="py-2 pr-4 capitalize text-xs">{{ str_replace('_', ' ', $repayment->channel) }}</td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ format_money($repayment->amount) }}</td>
                                <td class="py-2 text-xs capitalize">{{ $repayment->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
    </div>
    @endif
@endif

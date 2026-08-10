@php
    $loan = $writeOffRequest->loan;
    $customer = $loan?->customer;
    $name = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
    $user = auth()->user();
@endphp

<x-admin.layout
    :title="'Write-off #'.$writeOffRequest->id"
    :heading="'Write-off request #'.$writeOffRequest->id"
    :subheading="($loan?->loan_number ?? '—').' · '.$name">

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex flex-wrap gap-3">
        <a href="{{ route('admin.write-off-requests.index') }}" class="text-sm font-semibold text-brand hover:text-brand-light">← All write-off requests</a>
        @if ($loan)
            <a href="{{ route('admin.loans.show', $loan) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-800">Loan profile</a>
        @endif
        @if ($writeOffRequest->arrearCase)
            <a href="{{ route('admin.arrear-cases.show', $writeOffRequest->arrearCase) }}" class="text-sm font-semibold text-gray-600 hover:text-gray-800">Collection case</a>
        @endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Request details</h2>
                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Loan</dt>
                        <dd class="font-mono font-semibold">
                            @if ($loan)
                                <a href="{{ route('admin.loans.show', $loan) }}" class="text-amber-700">{{ $loan->loan_number }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Status</dt>
                        <dd class="font-semibold">{{ $service->statusLabel($writeOffRequest->status) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Amount</dt>
                        <dd class="font-semibold text-red-700">{{ format_money($writeOffRequest->amount) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Outstanding</dt>
                        <dd>{{ $loan ? format_money($loan->outstanding_balance) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Recommended by</dt>
                        <dd>{{ $writeOffRequest->recommender?->name ?? ($writeOffRequest->auto_proposed ? 'Auto-proposed rule' : '—') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500 uppercase">Recommended at</dt>
                        <dd>{{ optional($writeOffRequest->recommended_at)->format('d M Y H:i') ?? '—' }}</dd>
                    </div>
                    @if ($writeOffRequest->rule)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase">Matching rule</dt>
                            <dd>{{ $writeOffRequest->rule->name }}</dd>
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-gray-500 uppercase mb-1">Reason</dt>
                        <dd class="whitespace-pre-line text-gray-700">{{ $writeOffRequest->reason }}</dd>
                    </div>
                    @if ($writeOffRequest->manager_notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase mb-1">Manager notes</dt>
                            <dd class="whitespace-pre-line text-gray-700">{{ $writeOffRequest->manager_notes }}</dd>
                        </div>
                    @endif
                    @if ($writeOffRequest->finance_notes)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase mb-1">Finance notes</dt>
                            <dd class="whitespace-pre-line text-gray-700">{{ $writeOffRequest->finance_notes }}</dd>
                        </div>
                    @endif
                    @if ($writeOffRequest->rejection_reason)
                        <div class="sm:col-span-2">
                            <dt class="text-xs text-gray-500 uppercase mb-1">Rejection reason</dt>
                            <dd class="whitespace-pre-line text-red-700">{{ $writeOffRequest->rejection_reason }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-900 mb-4">Approval trail</h2>
                <ol class="space-y-3 text-sm">
                    <li class="flex items-start gap-3">
                        <span class="mt-1 size-2 rounded-full {{ $writeOffRequest->recommended_at ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        <div>
                            <p class="font-semibold">Collections recommendation</p>
                            <p class="text-xs text-gray-500">
                                {{ $writeOffRequest->recommender?->name ?? ($writeOffRequest->auto_proposed ? 'System' : 'Pending') }}
                                · {{ optional($writeOffRequest->recommended_at)->format('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 size-2 rounded-full {{ $writeOffRequest->manager_approved_at ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        <div>
                            <p class="font-semibold">Manager approval</p>
                            <p class="text-xs text-gray-500">
                                {{ $writeOffRequest->managerApprover?->name ?? 'Pending' }}
                                · {{ optional($writeOffRequest->manager_approved_at)->format('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 size-2 rounded-full {{ $writeOffRequest->completed_at ? 'bg-emerald-500' : 'bg-gray-300' }}"></span>
                        <div>
                            <p class="font-semibold">Finance execution</p>
                            <p class="text-xs text-gray-500">
                                {{ $writeOffRequest->financeApprover?->name ?? 'Pending' }}
                                · {{ optional($writeOffRequest->completed_at)->format('d M Y H:i') ?? '—' }}
                            </p>
                        </div>
                    </li>
                </ol>
            </div>
        </div>

        <div class="space-y-4">
            @if ($writeOffRequest->isPending() && $service->canReject($user, $writeOffRequest))
                <form method="POST" action="{{ route('admin.write-off-requests.reject', $writeOffRequest) }}"
                      class="bg-white rounded-xl ring-1 ring-red-200 p-5 space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-red-900">Reject request</h3>
                    <textarea name="rejection_reason" rows="3" required placeholder="Reason for rejection"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">{{ old('rejection_reason') }}</textarea>
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm">Reject</button>
                </form>
            @endif

            @if ($writeOffRequest->status === \App\Models\WriteOffRequest::STATUS_RECOMMENDED && $service->canManagerApprove($user))
                <form method="POST" action="{{ route('admin.write-off-requests.manager-approve', $writeOffRequest) }}"
                      class="bg-white rounded-xl ring-1 ring-amber-200 p-5 space-y-3">
                    @csrf
                    <h3 class="text-sm font-semibold text-amber-900">Manager approval</h3>
                    <textarea name="manager_notes" rows="3" placeholder="Optional notes"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">{{ old('manager_notes') }}</textarea>
                    <button type="submit" class="w-full bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2.5 rounded-lg text-sm">Approve for finance</button>
                </form>
            @endif

            @if ($writeOffRequest->status === \App\Models\WriteOffRequest::STATUS_MANAGER_APPROVED && $service->canFinanceApprove($user) && $approvalRequired)
                <form method="POST" action="{{ route('admin.write-off-requests.finance-approve', $writeOffRequest) }}"
                      class="bg-white rounded-xl ring-1 ring-red-200 p-5 space-y-3"
                      @submit.prevent="window.confirmForm($el, {
                          title: @js('Execute write-off?'),
                          message: @js('Execute write-off for '.($loan?->loan_number ?? 'this loan').'? This posts to the General Ledger.'),
                          confirmLabel: @js('Execute write-off'),
                          confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                          tone: 'warning',
                      })">
                    @csrf
                    <h3 class="text-sm font-semibold text-red-900">Finance — execute write-off</h3>
                    <textarea name="finance_notes" rows="3" placeholder="Optional finance notes"
                              class="w-full rounded-lg border-gray-300 text-sm ring-1 ring-gray-200 px-3 py-2">{{ old('finance_notes') }}</textarea>
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2.5 rounded-lg text-sm">Execute write-off</button>
                </form>
            @endif
        </div>
    </div>
</x-admin.layout>

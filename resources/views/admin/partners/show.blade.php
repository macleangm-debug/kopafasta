<x-admin.show-page
    :title="$record->name"
    :heading="$record->name"
    :subheading="$record->vendor_number"
    :backUrl="route('admin.partners.all')"
    :editUrl="route('admin.partners.edit', $record)"
    :fields="array_filter([
        'Partner #'  => $record->vendor_number,
        'Name'      => $record->name,
        'Category'  => ucfirst(str_replace('_', ' ', $record->category)),
        'Status'    => ucfirst($record->status ?? ''),
        'Open tasks' => ($openTasks ?? collect())->count() > 0
            ? ($openTasks->count()).' ongoing'
            : 'None',
        'Phone'     => $record->phone,
        'Email'     => $record->email,
        'Deposit markup %' => $record->deposit_markup_percent,
        'Affiliate code' => $record->affiliate_code,
        'Registration discount %' => $record->registration_discount_percent,
        'Application discount %' => $record->application_discount_percent,
        'Commission %' => $record->affiliate_commission_percent,
        'Recovery commission %' => $record->recovery_commission_percent,
        'Recovery markup %' => $record->recovery_markup_percent,
        'Address'   => ['value' => $record->address, 'wide' => true],
        'Created'   => $record->created_at?->format('Y-m-d H:i'),
    ])">

@php
    $openTasks = $openTasks ?? collect();
    $openValuations = $openValuations ?? collect();
    $recentTasks = $recentTasks ?? collect();
    $taskRows = $openTasks->isNotEmpty() ? $openTasks : $recentTasks;
    $enrollmentApplication = $enrollmentApplication ?? null;
@endphp

@if ($enrollmentApplication)
    <a href="{{ route('admin.partner-applications.show', $enrollmentApplication) }}"
       class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-brand-muted/50 ring-1 ring-brand/15 px-5 py-4 hover:ring-brand/30">
        <div>
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">Enrollment dossier</p>
            <p class="text-sm font-bold text-gray-900 mt-0.5">What this partner submitted</p>
            <p class="text-xs text-gray-500 mt-1">Profile, coverage, identity, and documents from their application.</p>
        </div>
        <span class="text-sm font-semibold text-brand">Open screening →</span>
    </a>
@endif

<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 {{ $openTasks->isNotEmpty() ? 'ring-amber-200' : 'ring-gray-200' }} p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold {{ $openTasks->isNotEmpty() ? 'text-amber-900' : 'text-gray-700' }}">Tasks</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ $openTasks->count() }} ongoing
                @if ($openValuations->isNotEmpty())
                    · {{ $openValuations->count() }} open valuation{{ $openValuations->count() === 1 ? '' : 's' }}
                @endif
            </p>
        </div>
        <a href="{{ route('admin.partners.tasks', ['partner' => $record->id]) }}"
           class="text-sm font-semibold text-brand hover:underline">All tasks →</a>
    </div>

    @if ($taskRows->isEmpty())
        <p class="text-sm text-gray-500">No tasks on this partner yet.</p>
    @else
        <ul class="text-sm text-gray-800 divide-y divide-gray-100">
            @foreach ($taskRows as $task)
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    <span class="font-semibold">{{ ucfirst(str_replace('_', ' ', (string) $task->task_type)) }}</span>
                    <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ in_array($task->status, ['assigned', 'in_progress'], true) ? 'bg-amber-100 text-amber-900' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst(str_replace('_', ' ', (string) $task->status)) }}
                    </span>
                    @if ($task->loan_application_id)
                        <a href="{{ route('admin.loan-applications.show', $task->loan_application_id) }}" class="text-brand font-semibold hover:underline">Application #{{ $task->loan_application_id }}</a>
                    @endif
                    @if ($task->customer_name)
                        <span class="text-gray-500">{{ $task->customer_name }}</span>
                    @endif
                    @if ($task->due_at)
                        <span class="text-xs text-gray-500">Due {{ $task->due_at->format('d M Y') }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($openTasks->isNotEmpty())
        <form method="POST" action="{{ route('admin.partners.halt-open-work', $record) }}"
              class="mt-4"
              x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Halt open tasks?'),
                  message: @js('Open jobs will be cancelled. If another valuer covers the region, the work is reassigned. Completed reports are not deleted.'),
                  confirmLabel: @js('Halt open tasks'),
                  confirmClass: 'bg-amber-500 hover:bg-amber-600 text-white',
                  tone: 'warning',
              })">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-amber-950 bg-amber-200 hover:bg-amber-300 ring-1 ring-amber-400 px-4 py-2 rounded-xl">
                Halt open tasks
            </button>
        </form>
    @endif
</div>

@php $payouts = $payouts ?? collect(); @endphp
@if (auth()->user()?->hasPermission('finance.operations'))
<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-700">Payouts</h3>
            <p class="text-xs text-gray-500 mt-0.5">Partner wallet lines from completed jobs. PAID means cash has left the bank and the journal is posted.</p>
        </div>
        <a href="{{ route('admin.payments.ledger', ['direction' => 'out', 'tab' => 'partners']) }}"
           class="text-sm font-semibold text-brand hover:underline">Money ledger →</a>
    </div>
    @if ($payouts->isEmpty())
        <p class="text-sm text-gray-500">No payouts on this partner yet.</p>
    @else
        <ul class="text-sm divide-y divide-gray-100">
            @foreach ($payouts as $payout)
                <li class="py-2.5 first:pt-0 flex flex-wrap items-baseline justify-between gap-2">
                    <div class="min-w-0">
                        <a href="{{ route('admin.partner-payments.show', $payout) }}" class="font-semibold text-brand hover:underline">
                            {{ $payout->invoice_number }}
                        </a>
                        <span class="text-gray-500"> · {{ $payout->description ?: str_replace('_', ' ', (string) $payout->source_type) }}</span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="tabular-nums font-semibold">{{ format_money((float) $payout->amount) }}</span>
                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ match ($payout->status) {
                            'paid' => 'bg-emerald-100 text-emerald-800',
                            'approved' => 'bg-sky-100 text-sky-800',
                            'pending' => 'bg-amber-100 text-amber-900',
                            'cancelled' => 'bg-gray-100 text-gray-600',
                            default => 'bg-gray-100 text-gray-600',
                        } }}">{{ strtoupper((string) $payout->status) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endif

<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-1">Portal PIN</h3>
    <p class="text-xs text-gray-500 mb-4">
        Partners sign in with phone and a 4-digit PIN. Set a new PIN here, or re-issue activation so they create it themselves.
    </p>
    @if (session('partner_activation_url'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-950 break-all">
            Activation link (valid 14 days): {{ session('partner_activation_url') }}
        </div>
    @endif
    <div class="grid sm:grid-cols-2 gap-4">
        <form method="POST" action="{{ route('admin.partners.reset-pin', $record) }}" class="space-y-2">
            @csrf
            <label class="block text-xs font-semibold uppercase tracking-widest text-gray-500">Set new PIN</label>
            <input name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required
                   class="w-full rounded-xl border-gray-300 text-sm" placeholder="4 digits" autocomplete="off">
            <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-4 py-2 rounded-xl">
                Save PIN
            </button>
        </form>
        <form method="POST" action="{{ route('admin.partners.reissue-activation', $record) }}" class="space-y-2">
            @csrf
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-500">Re-activation</p>
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="notify_partner" value="1" class="rounded border-gray-300 text-brand">
                Also SMS / email the link
            </label>
            <button type="submit" class="inline-flex text-sm font-semibold text-slate-800 bg-white ring-1 ring-slate-200 hover:bg-slate-50 px-4 py-2 rounded-xl">
                Re-issue activation link
            </button>
        </form>
    </div>
</div>

@if ($affiliateStats ?? null)
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate performance</h3>
        <div class="grid sm:grid-cols-3 gap-4 text-sm">
            <div><span class="text-gray-500">Clicks</span><p class="text-xl font-bold">{{ format_number($affiliateStats['clicks']) }}</p></div>
            <div><span class="text-gray-500">Registrations</span><p class="text-xl font-bold">{{ format_number($affiliateStats['registrations']) }}</p></div>
            <div><span class="text-gray-500">Applications</span><p class="text-xl font-bold">{{ format_number($affiliateStats['applications']) }}</p></div>
        </div>
        @if ($record->affiliate_code)
            <p class="mt-4 text-xs text-gray-500">Link: {{ app(\App\Services\AffiliateService::class)->affiliateLink($record) }}</p>
        @endif
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate lifecycle</h3>
        @php $lifecycle = app(\App\Services\AffiliateLifecycleService::class); @endphp
        <p class="text-sm text-gray-600 mb-2">
            Status:
            <span class="font-semibold">{{ $lifecycle->label($lifecycle->statusFor($record)) }}</span>
        </p>
        @if ($record->affiliate_leaderboard_rank)
            <p class="text-sm text-gray-600 mb-2">Leaderboard rank: <span class="font-semibold">#{{ $record->affiliate_leaderboard_rank }}</span></p>
        @endif
        @if ($record->affiliate_evaluation_snapshot)
            @php $snap = $record->affiliate_evaluation_snapshot; @endphp
            <div class="grid sm:grid-cols-3 gap-3 text-sm mb-4">
                <div><span class="text-gray-500">KPI</span><p class="font-bold">{{ number_format((float) ($snap['kpi_score'] ?? 0), 1) }}</p></div>
                <div><span class="text-gray-500">Risk</span><p class="font-bold">{{ number_format((float) ($snap['risk_score'] ?? 0), 1) }}</p></div>
                <div><span class="text-gray-500">Fraud</span><p class="font-bold">{{ number_format((float) ($snap['fraud_score'] ?? 0), 1) }}</p></div>
            </div>
            <p class="text-xs text-gray-500 mb-4">Last evaluated {{ $snap['evaluated_at'] ?? '—' }} · Recommendation: {{ ucfirst($snap['recommendation'] ?? 'none') }}</p>
        @endif
        @if ($record->affiliate_lifecycle_note)
            <p class="text-xs text-gray-500 mb-4">Note: {{ $record->affiliate_lifecycle_note }}</p>
        @endif
        <form method="POST" action="{{ route('admin.partners.affiliate-lifecycle.update', $record) }}" class="grid sm:grid-cols-3 gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Set lifecycle status</label>
                <select name="status" class="w-full rounded-lg border-gray-300 text-sm">
                    @foreach ($lifecycle->statuses() as $status)
                        <option value="{{ $status }}" @selected($lifecycle->statusFor($record) === $status)>{{ $lifecycle->label($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-gray-700 mb-1">Reason (optional)</label>
                <input type="text" name="reason" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm" placeholder="Policy breach, manual review, reinstatement…">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2 rounded-lg">Update lifecycle</button>
            </div>
        </form>
    </div>

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        @php $fraudService = app(\App\Services\AffiliateFraudDetectionService::class); @endphp
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Fraud controls</h3>
        <p class="text-sm text-gray-600 mb-2">
            Risk flag:
            <span class="font-semibold capitalize">{{ $fraudService->label((string) ($record->affiliate_risk_flag ?? 'low')) }}</span>
        </p>
        @if ($record->affiliate_fraud_snapshot)
            @php $fraudSnap = $record->affiliate_fraud_snapshot; @endphp
            <p class="text-xs text-gray-500 mb-3">Last scan {{ $fraudSnap['scanned_at'] ?? '—' }} · Score {{ $fraudSnap['score'] ?? 0 }}</p>
            <ul class="text-sm text-gray-700 space-y-1 mb-4">
                @foreach (($fraudSnap['signals'] ?? []) as $signal)
                    <li class="text-xs">• {{ $signal['message'] ?? '' }}</li>
                @endforeach
            </ul>
        @endif
        <div class="flex flex-wrap gap-3 items-end">
            <form method="POST" action="{{ route('admin.partners.affiliate-fraud.scan', $record) }}">
                @csrf
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2 rounded-lg">Run fraud scan</button>
            </form>
            <form method="POST" action="{{ route('admin.partners.affiliate-risk-flag.update', $record) }}" class="flex flex-wrap gap-2 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Override risk flag</label>
                    <select name="risk_flag" class="rounded-lg border-gray-300 text-sm">
                        @foreach ($fraudService->flags() as $flag)
                            <option value="{{ $flag }}" @selected(($record->affiliate_risk_flag ?? 'low') === $flag)>{{ $fraudService->label($flag) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand text-sm font-semibold px-4 py-2 rounded-lg">Save flag</button>
            </form>
        </div>
    </div>

    @if (($affiliateEvaluations ?? collect())->isNotEmpty())
        <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Evaluation history</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="text-left py-2">Period</th>
                            <th class="text-left py-2">KPI</th>
                            <th class="text-left py-2">Risk</th>
                            <th class="text-left py-2">Fraud</th>
                            <th class="text-left py-2">Recommendation</th>
                            <th class="text-left py-2">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($affiliateEvaluations as $evaluation)
                            <tr>
                                <td class="py-2">{{ $evaluation->period_start?->format('d M') }} – {{ $evaluation->period_end?->format('d M Y') }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->kpi_score, 1) }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->risk_score, 1) }}</td>
                                <td class="py-2">{{ number_format((float) $evaluation->fraud_score, 1) }}</td>
                                <td class="py-2 capitalize">{{ $evaluation->recommendation }}</td>
                                <td class="py-2 capitalize">{{ $evaluation->action_taken ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate KYC</h3>
        <p class="text-sm text-gray-600 mb-4">
            Status:
            <span class="font-semibold {{ in_array($record->affiliate_kyc_status, ['verified', 'approved'], true) ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ ucfirst($record->affiliate_kyc_status ?? 'pending') }}
            </span>
        </p>
        <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
            @foreach ([
                'Selfie' => $record->affiliate_selfie_path,
                'ID document' => $record->affiliate_id_path,
                'Profile photo' => $record->affiliate_photo_path,
            ] as $label => $path)
                <div class="rounded-lg bg-gray-50 p-3">
                    <p class="text-xs text-gray-500 mb-2">{{ $label }}</p>
                    @if ($path)
                        <a href="{{ asset('storage/'.$path) }}" target="_blank" class="text-brand hover:underline text-xs">View file</a>
                    @else
                        <p class="text-xs text-gray-400">Not uploaded</p>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($record->affiliate_code)
            <p class="text-xs text-gray-500 mb-4">Public verification: <a href="{{ route('site.affiliate.verify', $record->affiliate_code) }}" class="text-brand hover:underline" target="_blank">{{ route('site.affiliate.verify', $record->affiliate_code) }}</a></p>
        @endif
        @if (in_array($record->affiliate_kyc_status, ['submitted', 'pending', 'rejected'], true) || filled($record->affiliate_selfie_path))
            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('admin.partners.affiliate-kyc.approve', $record) }}">
                    @csrf
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Approve KYC</button>
                </form>
                <form method="POST" action="{{ route('admin.partners.affiliate-kyc.reject', $record) }}">
                    @csrf
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Reject KYC</button>
                </form>
            </div>
        @endif
    </div>

    @if ($membership ?? null)
        <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Affiliate membership</h3>
            <div class="grid sm:grid-cols-3 gap-4 text-sm mb-4">
                <div>
                    <span class="text-gray-500">Status</span>
                    <p class="text-lg font-bold {{ $membership['active'] ? 'text-emerald-700' : 'text-amber-700' }}">{{ $membership['label'] }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Annual fee</span>
                    <p class="text-lg font-bold">{{ format_money($membership['fee']) }}</p>
                </div>
                <div>
                    <span class="text-gray-500">Expires</span>
                    <p class="text-lg font-bold">{{ $membership['expires_at']?->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
            @if ($membership['reference'])
                <p class="text-xs text-gray-500 mb-2">Payment reference: <span class="font-mono">{{ $membership['reference'] }}</span></p>
            @endif
            @if ($membership['due_at'])
                <p class="text-xs text-gray-500 mb-4">Pay-by window: {{ $membership['due_at']->format('d M Y H:i') }}</p>
            @endif
            @if ($membership['status'] === 'pending_payment')
                <div class="flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('admin.partners.membership.approve', $record) }}">
                        @csrf
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Approve payment</button>
                    </form>
                    <form method="POST" action="{{ route('admin.partners.membership.reject', $record) }}">
                        @csrf
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">Reject payment</button>
                    </form>
                </div>
            @endif
        </div>
    @endif
@endif

@if ($recoveryStats ?? null)
    <div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">Recovery performance</h3>
        <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-4 text-sm">
            <div><span class="text-gray-500">Assignments</span><p class="text-xl font-bold">{{ format_number($recoveryStats['assignments']) }}</p></div>
            <div><span class="text-gray-500">Active</span><p class="text-xl font-bold">{{ format_number($recoveryStats['active_cases']) }}</p></div>
            <div><span class="text-gray-500">Completed</span><p class="text-xl font-bold">{{ format_number($recoveryStats['completed_cases']) }}</p></div>
            <div><span class="text-gray-500">SLA breaches</span><p class="text-xl font-bold text-red-700">{{ format_number($recoveryStats['sla_breaches']) }}</p></div>
            <div><span class="text-gray-500">Commission earned</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_earned']) }}</p></div>
            <div><span class="text-gray-500">Commission paid</span><p class="text-xl font-bold">{{ format_money($recoveryStats['commission_paid']) }}</p></div>
        </div>
    </div>
@endif

<div class="mt-6 bg-white rounded-xl shadow-sm ring-1 ring-red-200/80 p-6">
    <h3 class="text-sm font-semibold text-red-700 mb-1">Danger zone</h3>
    <p class="text-xs text-gray-500 mb-3">
        Create the replacement partner first. Halt open work, then deactivate. Delete is only for partners with no history.
    </p>
    <div class="flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('admin.partners.destroy', $record) }}"
              id="partner-delete-form-{{ $record->id }}"
              x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Delete this partner?'),
                  message: @js('This permanently deletes the partner. Open or completed jobs cannot be deleted with them — halt open tasks, then Deactivate.'),
                  confirmLabel: @js('Delete'),
                  confirmClass: 'bg-red-600 hover:bg-red-700 text-white',
                  tone: 'warning',
              })">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 px-4 py-2 rounded-xl shadow-sm transition">
                Delete
            </button>
        </form>
        <form method="POST" action="{{ route('admin.partners.deactivate', $record) }}"
              x-data
              @submit.prevent="window.confirmForm($el, {
                  title: @js('Deactivate this partner?'),
                  message: @js('Open jobs are halted and offered to another partner. This partner is suspended, login is disabled, and history is kept.'),
                  confirmLabel: @js('Deactivate'),
                  confirmClass: 'bg-amber-500 hover:bg-amber-600 text-white',
                  tone: 'warning',
              })">
            @csrf
            <button type="submit"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-amber-900 bg-amber-100 hover:bg-amber-200 ring-1 ring-amber-300 px-4 py-2 rounded-xl shadow-sm transition">
                Deactivate
            </button>
        </form>
    </div>
</div>
</x-admin.show-page>

@php
    $seq = $sequence ?? [];
    $rows = $seq['sequence'] ?? [];
    $next = $seq['next_action'] ?? [];
    $policy = $seq['policy'] ?? [];
    $resolution = $policy['resolution'] ?? null;
    $declared = $seq['declared'] ?? [];
    $verified = $seq['verified'] ?? [];
    $deskGates = $gates ?? [];
@endphp
<div class="px-5 pt-4 pb-3 border-b border-gray-100 space-y-3 bg-slate-50/80">
    <div>
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Next action</p>
        <p class="text-sm font-bold text-slate-900 mt-0.5">{{ $next['label'] ?? 'Continue screening' }}</p>
        @if (! empty($next['detail']))
            <p class="text-sm text-slate-600 mt-0.5">{{ $next['detail'] }}</p>
        @endif
    </div>

    <ol class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
        @foreach ($rows as $row)
            @php
                $deskGateKey = (string) ($row['desk_gate'] ?? (($row['key'] ?? '') === 'declared' ? 'income' : ($row['key'] ?? 'income')));
                $deskMeta = is_array($deskGates[$deskGateKey] ?? null) ? $deskGates[$deskGateKey] : [];
                $status = (string) ($row['status'] ?? 'locked');
                $chip = (string) ($row['chip'] ?? '');
                if (in_array($row['key'] ?? '', ['identity', 'crb', 'collateral', 'final'], true)
                    && $status === 'open'
                    && $deskMeta !== []) {
                    if (! empty($deskMeta['complete'])) {
                        $status = 'passed';
                        $chip = 'Passed';
                    } elseif ((int) ($deskMeta['failed'] ?? 0) > 0) {
                        $status = 'attention';
                        $chip = 'Attention';
                    } elseif ((int) ($deskMeta['decided'] ?? 0) > 0) {
                        $status = 'in_progress';
                        $chip = ((int) $deskMeta['decided']).'/'.((int) $deskMeta['total']);
                    }
                }
                $shell = match ($status) {
                    'passed' => 'bg-emerald-50 ring-emerald-200 text-emerald-950 hover:ring-emerald-400',
                    'attention', 'fail', 'pending_rejection' => 'bg-rose-50 ring-rose-200 text-rose-950 hover:ring-rose-400',
                    'in_progress' => 'bg-amber-50 ring-amber-200 text-amber-950 hover:ring-amber-400',
                    'locked' => 'bg-white ring-slate-200 text-slate-500 hover:ring-slate-400',
                    default => 'bg-white ring-brand/20 text-slate-800 hover:ring-brand/50',
                };
            @endphp
            <li>
                <button type="button"
                        data-sequence-gate="{{ $row['key'] }}"
                        data-desk-gate="{{ $deskGateKey }}"
                        data-sequence-status="{{ $status }}"
                        @click="setGate(@js($deskGateKey))"
                        :class="gate === @js($deskGateKey) ? 'ring-2 ring-brand shadow-sm' : ''"
                        class="w-full text-left rounded-xl px-3 py-2.5 ring-1 transition {{ $shell }}">
                    <span class="flex items-start gap-2.5">
                        <span class="mt-0.5 shrink-0" aria-hidden="true">
                            @if ($status === 'passed')
                                <svg class="size-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.78-9.72a.75.75 0 00-1.06-1.06L9 10.94 7.28 9.22a.75.75 0 00-1.06 1.06l2.25 2.25a.75.75 0 001.06 0l4.25-4.25z" clip-rule="evenodd"/></svg>
                            @elseif (in_array($status, ['fail', 'pending_rejection'], true))
                                <svg class="size-5 text-rose-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
                            @elseif ($status === 'attention')
                                <svg class="size-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                            @elseif ($status === 'locked')
                                <svg class="size-5 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd"/></svg>
                            @elseif ($status === 'in_progress')
                                <svg class="size-5 text-amber-600" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>
                            @else
                                <svg class="size-5 text-brand" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h4.59l-2.1 1.95a.75.75 0 101.02 1.1l3.5-3.25a.75.75 0 000-1.1l-3.5-3.25a.75.75 0 10-1.02 1.1l2.1 1.95H6.75z" clip-rule="evenodd"/></svg>
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block text-xs font-bold">{{ $row['label'] }}</span>
                            <span class="block text-[11px] mt-0.5 font-semibold">{{ $chip }}</span>
                        </span>
                    </span>
                </button>
            </li>
        @endforeach
    </ol>

    @if (($seq['pending_rejection'] ?? false) && ($seq['remaining_label'] ?? null))
        <div class="rounded-xl bg-rose-50 ring-1 ring-rose-200 px-3 py-2.5">
            <p class="text-sm font-bold text-rose-950">
                {{ (($seq['park_gate'] ?? '') === 'verified') ? 'Verified affordability failed' : 'Initial affordability failed' }}
            </p>
            <p class="text-sm text-rose-800">Pending automatic rejection · {{ $seq['remaining_label'] }} remaining</p>
        </div>
    @endif

    @if (is_array($resolution) && ($resolution['blocking'] ?? false) && ! ($seq['pending_rejection'] ?? false))
        <div class="rounded-xl bg-amber-50 ring-1 ring-amber-200 px-3 py-2.5 space-y-2">
            <p class="text-sm font-bold text-amber-950">{{ $resolution['next_action'] ?? 'Resolve eligibility' }}</p>
            <p class="text-sm text-amber-900">{{ $resolution['detail'] ?? '' }}</p>
            <div class="flex flex-wrap gap-2">
                @if (($resolution['code'] ?? '') === \App\Services\CreditEligibilityPolicyService::ACTION_REPLACE_GUARANTOR)
                    <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'profiles']).'#guarantor-overview' }}"
                       class="inline-flex rounded-xl bg-brand text-white text-xs font-bold px-3 py-2">
                        {{ $resolution['cta'] ?? 'Replace guarantor' }}
                    </a>
                @elseif (in_array($resolution['code'] ?? '', [
                    \App\Services\CreditEligibilityPolicyService::ACTION_REPLACE_MEMBER,
                    \App\Services\CreditEligibilityPolicyService::ACTION_RESOLVE_MEMBERS,
                    \App\Services\CreditEligibilityPolicyService::ACTION_CONTINUE_ELIGIBLE,
                ], true))
                    <a href="{{ route('admin.loan-applications.show', ['loan_application' => $record, 'workspace' => 'checklist', 'security_tab' => 'group']).'#review-desk' }}"
                       class="inline-flex rounded-xl bg-brand text-white text-xs font-bold px-3 py-2">
                        {{ $resolution['cta'] ?? 'Replace member' }}
                    </a>
                    @if (! empty($resolution['allow_continue_without_failed']))
                        <span class="inline-flex rounded-xl bg-white ring-1 ring-amber-300 text-amber-950 text-xs font-bold px-3 py-2">
                            {{ $resolution['continue_cta'] ?? 'Continue with eligible members' }}
                        </span>
                    @endif
                @endif
            </div>
            <p class="text-xs text-amber-800">Further screening is waiting until this is resolved. History of replaced participants is kept.</p>
        </div>
    @endif

    @if (($declared['pass'] ?? false) && ($verified['status'] ?? '') === 'in_progress')
        <p class="text-xs text-slate-600">
            ✓ Initial affordability passed. Next: Review income statements (2.1 totals → 2.2 activity → 2.3 patterns → 2.4 affordability).
        </p>
    @endif
</div>

@php
    $seq = $sequence ?? [];
    $rows = $seq['sequence'] ?? [];
    $next = $seq['next_action'] ?? [];
    $policy = $seq['policy'] ?? [];
    $resolution = $policy['resolution'] ?? null;
    $declared = $seq['declared'] ?? [];
    $verified = $seq['verified'] ?? [];
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
            <li class="rounded-xl px-3 py-2 ring-1 {{ ($row['status'] ?? '') === 'locked' ? 'bg-white ring-slate-200 text-slate-500' : (($row['status'] ?? '') === 'pending_rejection' || ($row['status'] ?? '') === 'fail' ? 'bg-rose-50 ring-rose-200 text-rose-900' : 'bg-white ring-brand/20 text-slate-800') }}">
                <p class="text-xs font-bold">{{ $row['label'] }}</p>
                <p class="text-[11px] mt-0.5">{{ $row['chip'] }}</p>
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

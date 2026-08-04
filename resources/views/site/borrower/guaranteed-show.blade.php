<x-site.borrower-layout
    :title="brand_title(__('borrower.guaranteed.detail_title'))"
    active="loans"
    content-width="narrow">

    @php
        $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
        $productName = $row->product?->localizedName() ?? __('borrower.guarantor.loan');
        $listTab = $listTab ?? 'guaranteed';
        $timeline = $timeline ?? ['percent' => 0, 'steps' => []];
        $needsProfile = $row->needs_guarantor_profile ?? false;
        $profilePercent = (int) ($row->profile_percent ?? 0);
        $submitted = $row->application !== null
            && ! in_array((string) ($row->application_status['code'] ?? ''), ['pending_submission', ''], true);
        $statusLine = match (true) {
            $row->is_disbursed ?? false => __('borrower.loans_page.loan_statuses.active')
                ?? __('borrower.guaranteed.timeline_disbursed'),
            $needsProfile && $submitted => __('borrower.guaranteed.status_submitted_waiting_you'),
            $needsProfile => __('borrower.guaranteed.waiting_on_your_profile'),
            default => $row->stage_label ?? ($row->application_status['label'] ?? '—'),
        };
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.loans', ['tab' => $listTab]) }}"
           class="text-sm font-semibold text-brand hover:underline">
            ← {{ __('borrower.guaranteed.back_to_list') }}
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <x-site.borrower-page-header
        :eyebrow="__('borrower.loans_page.guarantor_badge')"
        :title="$productName"
        :subtitle="$borrowerName.' · '.$row->reference"
    >
        <x-slot:actions>
            <span class="inline-flex text-xs font-semibold rounded-full px-3 py-1.5 {{ $needsProfile ? 'bg-amber-100 text-amber-900' : 'bg-sky-100 text-sky-800' }}">
                {{ $statusLine }}
            </span>
        </x-slot:actions>
    </x-site.borrower-page-header>

    {{-- Summary first --}}
    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <p class="text-[10px] uppercase tracking-widest text-brand font-semibold mb-4">{{ __('borrower.loan_profile.summary_title') }}</p>
        <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.applications_list.amount') }}</p>
                <p class="font-semibold mt-1">{{ format_money($row->amount) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.borrower') }}</p>
                <p class="font-semibold mt-1">{{ $borrowerName }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.guaranteed.current_step') }}</p>
                <p class="font-semibold mt-1">{{ $statusLine }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                <p class="font-semibold mt-1">
                    @if ($row->loan)
                        {{ ucfirst((string) $row->loan_status) }}
                    @elseif ($submitted)
                        {{ __('borrower.guaranteed.loan_status_submitted') }}
                    @else
                        {{ __('borrower.loans_page.not_disbursed') }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    @if ($needsProfile)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="px-5 sm:px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loan_profile.profile_completion') }}</p>
                    <div class="flex items-center gap-3 mt-3 max-w-md">
                        <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $profilePercent }}%"></div>
                        </div>
                        <span class="text-sm font-bold tabular-nums text-gray-900">{{ $profilePercent }}%</span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 mt-2">{{ __('borrower.guaranteed.profile_block_title') }}</p>
                    <p class="text-sm text-gray-600 mt-1">{{ __('borrower.guaranteed.profile_block_body', ['percent' => $profilePercent]) }}</p>
                    @if (! empty($row->deadline_label) || isset($row->deadline_days_left))
                        <x-site.deadline-badge
                            :label="$row->deadline_label"
                            :days-left="$row->deadline_days_left ?? null"
                            :date="$row->deadline_date ?? null"
                            :purpose="__('borrower.loan_profile.deadline_purpose_your_profile')"
                            :urgent="(bool) ($row->deadline_urgent ?? false)"
                            :expired="(bool) ($row->deadline_expired ?? false)"
                        />
                    @endif
                </div>
                <a href="{{ $row->profile_url }}"
                   class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                    {{ __('borrower.guarantor.complete_profile') }}
                </a>
            </div>
        </div>
    @elseif ($row->pending_hint && ! ($row->is_disbursed ?? false))
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="px-5 sm:px-6 py-4">
                <p class="text-sm text-gray-700">{{ $row->pending_hint }}</p>
            </div>
        </div>
    @endif

    @if ($row->in_arrears)
        <div class="mb-6 rounded-2xl bg-red-50 ring-1 ring-red-200 px-5 py-4 text-sm text-red-800">
            <p class="font-semibold">{{ __('borrower.guaranteed.arrears_alert_title') }}</p>
            <p class="mt-1">{{ __('borrower.guaranteed.arrears_alert_body', ['balance' => format_money($row->outstanding ?? 0)]) }}</p>
        </div>
    @endif

    @if (! empty($timeline['steps']))
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="bg-gradient-to-br from-brand-muted/40 to-white px-5 sm:px-6 py-5">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guaranteed.status_checklist_title') }}</p>
                <ol class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($timeline['steps'] as $step)
                        <li @class([
                            'rounded-xl px-3.5 py-3 ring-1 flex items-start gap-2.5',
                            'bg-brand text-white ring-brand' => $step['complete'] ?? false,
                            'bg-brand-gold/25 text-brand ring-brand-gold/50' => ! ($step['complete'] ?? false) && ($step['current'] ?? false),
                            'bg-white text-gray-600 ring-gray-200' => ! ($step['complete'] ?? false) && ! ($step['current'] ?? false),
                        ])>
                            <span class="mt-0.5 shrink-0 size-5 rounded-full grid place-items-center text-[10px] font-bold
                                {{ ($step['complete'] ?? false) ? 'bg-brand-gold text-brand' : (($step['current'] ?? false) ? 'bg-brand text-brand-gold' : 'bg-gray-100 text-gray-400') }}">
                                {{ ($step['complete'] ?? false) ? '✓' : '·' }}
                            </span>
                            <span class="text-sm font-semibold leading-snug">{{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    @endif

    @if ($row->loan)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15 p-5 sm:p-6">
            <h2 class="font-semibold mb-1">{{ __('borrower.guaranteed.repayment_progress') }}</h2>
            <div class="flex items-center justify-between text-xs text-gray-500 mb-2 mt-3">
                <span>{{ __('borrower.loans_page.repaid_pct', ['pct' => format_number($row->repaid_percent, 0)]) }}</span>
                @if ($row->next_due_date)
                    <span>{{ __('borrower.guaranteed.next_due', ['date' => \Carbon\Carbon::parse($row->next_due_date)->format('d M Y')]) }}</span>
                @endif
            </div>
            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden mb-5">
                <div class="h-full {{ $row->in_arrears ? 'bg-red-500' : 'bg-brand' }}" style="width: {{ min(100, max(0, $row->repaid_percent)) }}%"></div>
            </div>

            @if ($row->schedule->isNotEmpty())
                <div class="overflow-x-auto rounded-xl border border-gray-100">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase text-gray-500 bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="text-left px-4 py-2">#</th>
                                <th class="text-left px-4 py-2">{{ __('borrower.guaranteed.due_date') }}</th>
                                <th class="text-right px-4 py-2">{{ __('borrower.guaranteed.installment') }}</th>
                                <th class="text-right px-4 py-2">{{ __('borrower.guaranteed.paid') }}</th>
                                <th class="text-center px-4 py-2">{{ __('borrower.guaranteed.status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($row->schedule as $installment)
                                @php
                                    $isOverdue = $installment->status !== 'paid' && \Carbon\Carbon::parse($installment->due_date)->isPast();
                                    $st = $isOverdue ? 'overdue' : $installment->status;
                                    $installmentStatuses = __('borrower.guaranteed.installment_statuses');
                                    $color = match ($st) {
                                        'paid' => 'bg-emerald-100 text-emerald-700',
                                        'overdue' => 'bg-red-100 text-red-700',
                                        default => 'bg-amber-100 text-amber-700',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2.5 font-mono text-xs">{{ $installment->installment_no }}</td>
                                    <td class="px-4 py-2.5">{{ \Carbon\Carbon::parse($installment->due_date)->format('d M Y') }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold">{{ format_number($installment->total_due) }}</td>
                                    <td class="px-4 py-2.5 text-right text-gray-500">{{ format_number($installment->amount_paid) }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="text-xs font-semibold rounded-full px-2 py-0.5 {{ $color }}">{{ $installmentStatuses[$st] ?? ucfirst($st) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($row->restructure || $row->top_up)
        <div class="mb-6 glass-card overflow-hidden ring-1 ring-brand/15 p-5 sm:p-6">
            <h2 class="font-semibold mb-4">{{ __('borrower.guaranteed.modifications_title') }}</h2>
            @php $modificationStatuses = __('borrower.guaranteed.modification_statuses'); @endphp
            <dl class="space-y-4 text-sm">
                @if ($row->restructure)
                    <div class="rounded-xl bg-brand-muted/30 px-4 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loan_actions.restructure') }}</dt>
                        <dd class="mt-1 capitalize">{{ str_replace('_', ' ', $row->restructure->restructure_type) }} · <span class="font-semibold">{{ $modificationStatuses[$row->restructure->status] ?? ucfirst($row->restructure->status) }}</span></dd>
                    </div>
                @endif
                @if ($row->top_up)
                    <div class="rounded-xl bg-brand-muted/30 px-4 py-3">
                        <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.loan_actions.top_up') }}</dt>
                        <dd class="mt-1">{{ format_money($row->top_up->requested_amount) }} · <span class="font-semibold">{{ $modificationStatuses[$row->top_up->status] ?? ucfirst($row->top_up->status) }}</span></dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif

    <div class="mb-2 glass-card overflow-hidden ring-1 ring-brand/15">
        <div class="px-5 sm:px-6 py-4">
            <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guaranteed.responsibility_title') }}</p>
            <p class="text-sm text-gray-700 mt-1">{{ __('borrower.guaranteed.responsibility_body_short') }}</p>
        </div>
    </div>

</x-site.borrower-layout>

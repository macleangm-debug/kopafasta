{{-- Loan-profile-style preview for one accepted guarantee (guarantor side). --}}
@php
    $borrowerName = $row->borrower?->legalDisplayName() ?? __('borrower.loans_page.borrower');
    $productName = $row->product?->name ?? __('borrower.guarantor.loan');
    $needsProfile = (bool) ($row->needs_guarantor_profile ?? false);
    $profilePercent = (int) ($row->profile_percent ?? 0);
    $detailUrl = route('site.borrower.guaranteed.show', $row->link);
    $timeline = app(\App\Services\GuaranteedLoanService::class)->progressTimeline($row);
    $progressPercent = (int) ($timeline['percent'] ?? 0);
    $steps = $timeline['steps'] ?? [];
    $isTerminal = (bool) ($row->is_terminal ?? false);
@endphp

<div class="max-w-3xl {{ $isTerminal ? 'opacity-80' : '' }}">
    <div class="mb-6">
        <p class="text-xs uppercase tracking-widest text-brand font-semibold mb-1">{{ __('borrower.loans_page.guarantor_badge') }}</p>
        <h2 class="text-2xl sm:text-3xl font-bold text-brand tracking-tight">{{ $productName }}</h2>
        <p class="text-sm text-gray-500 mt-1">{{ $borrowerName }} · {{ $row->reference }}</p>
    </div>

    {{-- 1. At a glance (mirrors loan profile draft summary) --}}
    <div class="mb-6 glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand-muted/50 to-white px-5 sm:px-6 py-5 border-b border-gray-100/80">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.guaranteed.detail_eyebrow') }}</p>
                    <h3 class="text-lg sm:text-xl font-bold text-gray-900 mt-1">{{ __('borrower.guaranteed.detail_glance_title') }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $row->pending_hint ?? $row->stage_label }}</p>
                </div>
                @if ($needsProfile)
                    <a href="{{ $row->profile_url }}"
                       class="inline-flex items-center justify-center font-bold px-8 py-3.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                        {{ __('borrower.guarantor.complete_profile') }}
                    </a>
                @else
                    <a href="{{ $detailUrl }}"
                       class="inline-flex items-center justify-center font-bold px-8 py-3.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand shadow-sm">
                        {{ __('borrower.guaranteed.view_details') }}
                    </a>
                @endif
            </div>

            <div class="mt-5 rounded-xl bg-white ring-1 ring-gray-200/80 px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">{{ __('borrower.loan_profile.application_progress') }}</p>
                <div class="flex items-center gap-3 mt-2">
                    <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $progressPercent)) }}%"></div>
                    </div>
                    <span class="text-sm font-bold tabular-nums text-gray-900">{{ $progressPercent }}%</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">{{ $row->stage_label }}</p>
            </div>

            <div class="mt-4 rounded-2xl overflow-hidden ring-1 ring-brand/15 bg-gradient-to-br from-brand-muted/40 via-white to-white px-4 py-3">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.loans_page.loan_amount') }}</p>
                <p class="text-sm text-gray-700 mt-0.5">
                    {{ format_money($row->amount) }}
                    · {{ __('borrower.loans_page.not_disbursed') }}
                </p>
            </div>
        </div>
    </div>

    {{-- 2. Profile completion (same card as loan profile) --}}
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
                    <p class="text-sm text-gray-600 mt-2">{{ __('borrower.loan_profile.profile_completion_hint') }}</p>
                </div>
                <a href="{{ $row->profile_url }}"
                   class="inline-flex items-center justify-center font-bold px-5 py-2.5 rounded-xl text-sm shrink-0 bg-brand-gold hover:bg-yellow-400 text-brand">
                    {{ __('borrower.loan_profile.complete_profile') }}
                </a>
            </div>
        </div>
    @endif

    {{-- 3. Summary --}}
    <div class="glass-card p-5 mb-6 ring-1 ring-brand/15">
        <div class="mb-4">
            <h3 class="font-semibold">{{ __('borrower.loan_profile.summary_title') }}</h3>
        </div>
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
                <p class="font-semibold mt-1">{{ $row->stage_label }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-400">{{ __('borrower.loans_page.loan_status') }}</p>
                <p class="font-semibold mt-1">{{ $row->loan ? ucfirst((string) $row->loan_status) : __('borrower.loans_page.not_disbursed') }}</p>
            </div>
        </div>
    </div>

    {{-- 4. Stage checklist --}}
    @if (! empty($steps))
        <div class="mb-2 glass-card overflow-hidden ring-1 ring-brand/15">
            <div class="bg-gradient-to-r from-brand-muted/50 to-white px-5 py-4 border-b border-brand/10">
                <p class="text-[10px] uppercase tracking-widest text-brand font-semibold">{{ __('borrower.guaranteed.progress_title') }}</p>
            </div>
            <ol class="px-5 py-4 space-y-3">
                @foreach ($steps as $step)
                    <li class="flex items-start gap-3 text-sm">
                        <span @class([
                            'mt-0.5 shrink-0 size-5 rounded-full grid place-items-center text-[10px] font-bold',
                            'bg-brand text-brand-gold' => $step['complete'] ?? false,
                            'bg-brand-gold text-brand' => ! ($step['complete'] ?? false) && ($step['current'] ?? false),
                            'bg-gray-100 text-gray-400' => ! ($step['complete'] ?? false) && ! ($step['current'] ?? false),
                        ])>
                            {{ ($step['complete'] ?? false) ? '✓' : '·' }}
                        </span>
                        <span @class([
                            'leading-snug',
                            'font-semibold text-gray-900' => $step['current'] ?? false,
                            'text-gray-600' => ! ($step['current'] ?? false),
                        ])>{{ $step['label'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>

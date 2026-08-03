@php
    $customer = $review['customer'];
    $product = $review['product'];
    $stage = $record->current_stage ?? 'submitted';
    $isDisbursementStage = in_array($stage, ['disbursement'], true) || $record->status === 'disbursed';
    $checklist = app(\App\Services\ApplicationDisbursementReadinessService::class)
        ->borrowerDisbursementChecklist($record);
    $doneCount = collect($checklist)->where('complete', true)->count();
    $totalCount = max(1, count($checklist));
    $readyPct = (int) round(($doneCount / $totalCount) * 100);
    $approvedAmount = (float) ($record->offered_amount ?: $record->recommended_amount ?: $record->requested_amount);
    $offerStatus = (string) ($record->offer_status ?: '—');
    $offerTone = match ($offerStatus) {
        'accepted' => 'from-emerald-600 to-emerald-800',
        'declined' => 'from-rose-600 to-rose-800',
        'pending_borrower' => 'from-amber-500 to-amber-700',
        default => 'from-brand to-brand-light',
    };
    $readyTone = $readyPct >= 100
        ? 'bg-emerald-50 text-emerald-900 ring-emerald-200'
        : ($readyPct >= 50 ? 'bg-amber-50 text-amber-950 ring-amber-200' : 'bg-rose-50 text-rose-900 ring-rose-200');
@endphp

{{-- ── Credit management / disbursement desk ─────────────────────── --}}
<section class="space-y-4 mb-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">
                {{ $isDisbursementStage ? 'Disbursement workspace' : 'Credit management workspace' }}
            </p>
            <h2 class="text-lg font-bold text-gray-900 mt-0.5">
                {{ $isDisbursementStage ? 'Release this facility' : 'Finish post-approval' }}
            </h2>
            <p class="text-sm text-gray-500 mt-0.5">
                Screening and committee are done. Focus on offer, fees, contract, payout details, and release — not re-scoring CRB.
            </p>
        </div>
        <span class="text-xs font-semibold rounded-full px-3 py-1.5 bg-brand-gold/30 text-brand ring-1 ring-brand-gold/40">
            {{ $workflow->stageLabel($stage) }}
        </span>
    </div>

    <div class="grid lg:grid-cols-12 gap-4">
        <div class="lg:col-span-5 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
                <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Approved facility</p>
                <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money($approvedAmount) }}</p>
                <p class="text-sm text-white/75 mt-1">
                    {{ $record->offered_tenure_months ?? $record->requested_tenure_months }} months
                    @if ($product) · {{ $product->name }} @endif
                </p>
            </div>
            <div class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Borrower</p>
                    <p class="font-semibold text-gray-900 mt-0.5 truncate">{{ $customer->full_name }}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Member</p>
                    <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $customer->member_no ?? '—' }}</p>
                </div>
                @if ($record->recommended_amount)
                    <div>
                        <p class="text-[10px] uppercase tracking-widest text-gray-500">From committee</p>
                        <p class="font-bold text-brand mt-0.5">{{ format_money((float) $record->recommended_amount) }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-[10px] uppercase tracking-widest text-gray-500">Approved</p>
                    <p class="font-semibold text-gray-900 mt-0.5">{{ optional($record->approved_at)->format('d M Y') ?? '—' }}</p>
                </div>
            </div>
        </div>

        <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $offerTone }} text-white p-5">
            <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Offer status</p>
            <p class="text-2xl font-bold mt-2 capitalize tracking-tight">
                {{ $offerStatus === '—' ? '—' : str_replace('_', ' ', $offerStatus) }}
            </p>
            @if ($record->offered_amount)
                <p class="text-sm text-white/85 mt-3 tabular-nums">{{ format_money((float) $record->offered_amount) }}</p>
            @else
                <p class="text-sm text-white/80 mt-3">No offer amount on file yet.</p>
            @endif
        </div>

        <div class="lg:col-span-4 rounded-2xl ring-1 p-5 {{ $readyTone }} shadow-sm">
            <p class="text-[10px] uppercase tracking-widest font-semibold opacity-80">Release readiness</p>
            <div class="flex items-end gap-1.5 mt-2">
                <span class="text-4xl font-bold leading-none tabular-nums">{{ $readyPct }}</span>
                <span class="text-sm font-semibold pb-1 opacity-70">%</span>
            </div>
            <p class="text-sm font-bold mt-2">{{ $doneCount }} of {{ count($checklist) }} checklist items</p>
            <div class="mt-3 h-2 rounded-full bg-black/10 overflow-hidden">
                <div class="h-full rounded-full bg-current opacity-70" style="width: {{ max(4, $readyPct) }}%"></div>
            </div>
        </div>
    </div>

    {{-- Checklist + actions --}}
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-muted/40 to-white">
            <p class="text-[10px] uppercase tracking-[0.2em] text-brand font-semibold">
                {{ $isDisbursementStage ? 'Release checklist' : 'Post-approval checklist' }}
            </p>
            <h3 class="text-base font-bold text-gray-900 mt-0.5">What still blocks payout</h3>
        </div>
        <div class="p-5 grid sm:grid-cols-2 gap-3">
            @foreach ($checklist as $item)
                @php
                    $done = (bool) ($item['complete'] ?? false);
                    $label = $item['label'] ?? 'Item';
                    $status = (string) ($item['status'] ?? ($done ? 'complete' : 'pending'));
                @endphp
                <div @class([
                    'rounded-xl px-4 py-3 ring-1 text-sm',
                    'bg-emerald-50 ring-emerald-200 text-emerald-900' => $done,
                    'bg-gray-50 ring-gray-200 text-gray-600' => in_array($status, ['locked', 'not_required'], true),
                    'bg-amber-50 ring-amber-200 text-amber-950' => ! $done && ! in_array($status, ['locked', 'not_required'], true),
                ])>
                    <p class="font-semibold">{{ $label }}</p>
                    <p class="text-xs mt-1 opacity-80 capitalize">{{ str_replace('_', ' ', $status) }}</p>
                </div>
            @endforeach
        </div>

        @if (($availableActions ?? collect())->isNotEmpty())
            <div class="px-5 pb-5 border-t border-brand/10 pt-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-brand mb-3">
                    {{ $isDisbursementStage ? 'Disbursement actions' : 'Management actions' }}
                </p>
                @include('admin.loan-applications._workflow_actions')
            </div>
        @endif
    </div>

    @include('admin.loan-applications._loan-link')
    @include('admin.loan-applications.review._contract')
</section>

@include('admin.loan-applications.review._borrower_file_tabs')

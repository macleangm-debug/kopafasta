@php
    $customer = $customer ?? ($review['customer'] ?? null);
    $product = $product ?? ($review['product'] ?? null);
    $linkedLoan = $linkedLoan ?? $record->loan;
    $servicing = $servicing ?? null;
    $signedContract = $signedContract ?? null;
    $workspaceUrl = $workspaceUrl ?? null;
    $letterDownloadUrl = $letterDownloadUrl ?? fn ($agreement) => route('admin.loan-agreements.download', $agreement);
    $previewMode = $previewMode ?? 'admin';
    $lettersHref = is_callable($workspaceUrl) ? $workspaceUrl('documents') : '#loan-letters';

    $healthInArrears = (bool) ($servicing['in_arrears'] ?? false);
    $healthTone = match (true) {
        ($linkedLoan?->status === 'defaulted') => 'from-rose-700 to-rose-900',
        $healthInArrears => 'from-amber-500 to-amber-700',
        default => 'from-emerald-600 to-emerald-800',
    };

    $signedHasFile = (bool) $signedContract?->file_path;
    $signedLabel = match (true) {
        $signedContract?->document_type === 'final_loan_contract' && $signedHasFile => 'Executed',
        $signedContract?->isSigned() && $signedHasFile => 'Signed',
        $signedHasFile => 'On file',
        default => 'Not on file',
    };
    $signedTone = match ($signedLabel) {
        'Executed', 'Signed' => 'from-emerald-600 to-emerald-800',
        'On file' => 'from-brand to-brand-light',
        default => 'from-slate-500 to-slate-700',
    };
    $signedPreviewUrl = ($signedHasFile && is_callable($letterDownloadUrl))
        ? $letterDownloadUrl($signedContract)
        : null;
@endphp

<div class="grid lg:grid-cols-12 gap-4">
    <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
        <div class="bg-gradient-to-br from-brand via-brand to-brand-light px-5 py-4 text-white">
            <p class="text-[10px] uppercase tracking-widest text-brand-gold font-semibold">Outstanding</p>
            <p class="text-2xl font-bold mt-1 tabular-nums">{{ format_money((float) ($servicing['outstanding_balance'] ?? $linkedLoan->outstanding_balance ?? 0)) }}</p>
            <p class="text-sm text-white/75 mt-1">
                {{ $servicing['tenure_months'] ?? $linkedLoan->tenure_months }} months
                @if ($product) · {{ $product->name }} @endif
            </p>
        </div>
        <div class="px-5 py-4 grid grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Loan</p>
                <p class="font-semibold text-gray-900 mt-0.5 font-mono text-xs">{{ $linkedLoan->loan_number }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Principal</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ format_money((float) $linkedLoan->principal_amount) }}</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Paid</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ number_format((float) ($servicing['progress_pct'] ?? 0), 0) }}%</p>
            </div>
            <div>
                <p class="text-[10px] uppercase tracking-widest text-gray-500">Disbursed</p>
                <p class="font-semibold text-gray-900 mt-0.5">{{ optional($linkedLoan->disbursement_date ?? $record?->disbursed_at)->format('d M Y') ?? '—' }}</p>
            </div>
        </div>
    </div>

    <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $healthTone }} text-white px-5 py-5">
        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Repayment health</p>
        <p class="text-2xl font-bold mt-1">{{ $servicing['status_label'] ?? display_label($linkedLoan->status, 'loan_status') }}</p>
        <p class="text-sm text-white/85 mt-3">
            @if (! empty($servicing['next_due_amount']))
                Next {{ format_money((float) $servicing['next_due_amount']) }}
                @if (! empty($servicing['next_due_date']))
                    · {{ \Illuminate\Support\Carbon::parse($servicing['next_due_date'])->format('d M Y') }}
                @endif
            @else
                No remaining instalment
            @endif
        </p>
        <div class="mt-4 grid grid-cols-2 gap-2">
            <div class="rounded-xl bg-white/10 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-white/60">Days past due</p>
                <p class="text-sm font-bold mt-0.5 tabular-nums">{{ (int) ($servicing['days_past_due'] ?? 0) }}</p>
            </div>
            <div class="rounded-xl bg-white/10 px-3 py-2.5">
                <p class="text-[10px] uppercase tracking-wider text-white/60">In arrears</p>
                <p class="text-sm font-bold mt-0.5 tabular-nums">{{ format_money((float) ($servicing['amount_in_arrears'] ?? 0)) }}</p>
            </div>
        </div>
    </div>

    <div class="lg:col-span-3 rounded-2xl overflow-hidden shadow-sm ring-1 ring-black/5 bg-gradient-to-br {{ $signedTone }} text-white px-5 py-5">
        <p class="text-[10px] uppercase tracking-widest text-white/70 font-semibold">Signed contract</p>
        <p class="text-2xl font-bold mt-1">{{ $signedLabel }}</p>
        <p class="text-sm text-white/85 mt-3">
            @if ($signedContract)
                {{ $signedContract->reference }}
                @if ($signedContract->signed_at)
                    · {{ $signedContract->signed_at->format('d M Y') }}
                @endif
            @else
                Executed contract not generated yet
            @endif
        </p>
        @if ($previewMode === 'partner' && $signedPreviewUrl)
            <x-site.document-view-button
                :url="$signedPreviewUrl"
                type="pdf"
                label="Preview signed contract →"
                class="mt-4 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition text-white" />
        @else
            <a href="{{ $lettersHref }}"
               class="mt-4 inline-flex text-xs font-semibold rounded-lg bg-white/15 hover:bg-white/25 px-3 py-1.5 transition">
                Preview signed contract →
            </a>
        @endif
    </div>

    <div class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm px-5 py-5">
        <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Borrower</p>
        <p class="text-lg font-bold text-gray-900 mt-1 truncate">{{ $customer?->full_name ?? '—' }}</p>
        <p class="text-xs text-gray-500 mt-1 font-mono">{{ $customer?->member_no ?? '—' }}</p>
        <p class="text-sm text-gray-700 mt-3">{{ $customer?->phone ?? '—' }}</p>
        <p class="text-xs text-gray-500 mt-1">
            Instalments {{ (int) ($servicing['installments_paid'] ?? 0) }} / {{ (int) ($servicing['installments_total'] ?? 0) }}
        </p>
    </div>
</div>

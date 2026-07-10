<x-site.borrower-layout :title="brand_title($payment->reference)" active="payments" content-width="wide">

    <div class="mb-5">
        <a href="{{ route('site.borrower.payments') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.payments_page.back_history') }}</a>
    </div>

    @if (session('status'))
        <div class="mb-5 rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-5 rounded-2xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    @php
        $badge = match ($payment->status) {
            'verified', 'paid' => 'bg-emerald-500/20 text-emerald-100 ring-emerald-400/40',
            'rejected' => 'bg-red-500/20 text-red-100 ring-red-400/40',
            'clarification_requested' => 'bg-sky-500/20 text-sky-100 ring-sky-400/40',
            default => 'bg-amber-500/20 text-amber-100 ring-amber-400/40',
        };
    @endphp

    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-brand via-brand to-brand/90 text-white shadow-lg shadow-brand/20 mb-6">
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 18% 20%, #fff 0, transparent 42%), radial-gradient(circle at 88% 0%, #fbbf24 0, transparent 38%);"></div>
        <div class="relative px-5 sm:px-8 py-7 sm:py-8">
            <p class="text-[10px] uppercase tracking-[0.22em] font-semibold text-white/70">{{ __('borrower.payments_page.show.payment_reference') }}</p>
            <p class="mt-2 font-mono text-lg sm:text-xl font-bold tracking-tight">{{ $payment->reference }}</p>
            <p class="mt-4 text-[10px] uppercase tracking-widest text-white/60">{{ __('borrower.payments_page.show.amount') }}</p>
            <p class="mt-1 text-3xl sm:text-4xl font-extrabold tabular-nums tracking-tight text-amber-300">
                {{ format_money((float) $payment->amount) }}
            </p>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">{{ $payment->statusLabel() }}</span>
                <span class="text-xs text-white/70">{{ $payment->created_at?->format('d M Y') }}</span>
            </div>
        </div>
    </section>

    <div class="max-w-2xl space-y-5">
        <dl class="grid sm:grid-cols-2 gap-5">
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.payments_page.show.type') }}</dt>
                <dd class="mt-1.5 text-sm font-semibold text-gray-900">{{ $payment->typeLabel() }}</dd>
            </div>
            <div>
                <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.payments_page.show.method') }}</dt>
                <dd class="mt-1.5 text-sm font-semibold text-gray-900">{{ $payment->methodLabel() }}</dd>
            </div>
            @if ($payment->mobile_number)
                <div class="sm:col-span-2">
                    <dt class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold">{{ __('borrower.payments_page.show.mobile_number') }}</dt>
                    <dd class="mt-1.5 font-mono text-sm font-semibold text-gray-900">{{ $payment->mobile_number }}</dd>
                </div>
            @endif
        </dl>

        @if ($mobileDetails && $mobileDetails['number'])
            <div class="rounded-2xl bg-gradient-to-b from-sky-50 to-white ring-1 ring-sky-200/80 px-5 py-5">
                <p class="text-[10px] uppercase tracking-widest text-sky-700 font-semibold mb-3">{{ __('borrower.payments_page.show.mobile_details') }}</p>
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.show.provider') }}</dt><dd class="font-semibold mt-0.5">{{ $mobileDetails['provider'] }}</dd></div>
                    <div><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.show.number') }}</dt><dd class="font-mono font-semibold mt-0.5">{{ $mobileDetails['number'] }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.show.instructions') }}</dt><dd class="font-medium mt-0.5 text-sky-950">{{ $mobileDetails['instructions'] }}</dd></div>
                </dl>
            </div>
        @endif

        @if ($bankDetails)
            <div class="rounded-2xl bg-gradient-to-b from-sky-50 to-white ring-1 ring-sky-200/80 px-5 py-5">
                <p class="text-[10px] uppercase tracking-widest text-sky-700 font-semibold mb-3">{{ __('borrower.payments_page.show.bank_details') }}</p>
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.create.bank_label') }}</dt><dd class="font-semibold mt-0.5">{{ $bankDetails['bank_name'] }}</dd></div>
                    <div><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.create.account_name') }}</dt><dd class="font-semibold mt-0.5">{{ $bankDetails['account_name'] }}</dd></div>
                    <div><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.create.account_number') }}</dt><dd class="font-mono font-semibold mt-0.5">{{ $bankDetails['account_number'] }}</dd></div>
                    <div><dt class="text-xs text-sky-700">{{ __('borrower.payments_page.show.reference') }}</dt><dd class="font-mono font-semibold mt-0.5">{{ $payment->reference }}</dd></div>
                </dl>
            </div>
        @endif

        <div>
            <p class="text-[10px] uppercase tracking-widest text-gray-400 font-semibold mb-1.5">{{ __('borrower.payments_page.show.proof_submitted') }}</p>
            @if ($payment->hasProof())
                <p class="text-sm font-medium text-gray-800">{{ $payment->proof_original_name ?? __('borrower.payments_page.show.document_uploaded') }}</p>
            @else
                <p class="text-sm text-gray-500">{{ __('borrower.payments_page.show.no_proof') }}</p>
            @endif
        </div>

        @if ($payment->verification_notes)
            <div class="rounded-2xl bg-brand-muted/30 ring-1 ring-brand/10 px-5 py-4">
                <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1.5">{{ __('borrower.payments_page.show.verification_notes') }}</p>
                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $payment->verification_notes }}</p>
            </div>
        @endif

        @if ($payment->payment_method === 'bank_transfer' && $payment->isPending())
            <div class="rounded-2xl ring-1 ring-amber-200 bg-amber-50/60 px-5 py-5">
                <h3 class="text-sm font-semibold text-amber-950 mb-3">{{ __('borrower.payments_page.show.upload_proof_heading') }}</h3>
                <form method="POST" action="{{ route('site.borrower.payments.proof', $payment) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="file" name="proof" required accept=".jpg,.jpeg,.png,.pdf"
                           class="text-sm text-gray-600 file:mr-3 file:rounded-xl file:border-0 file:bg-amber-200 file:px-4 file:py-2.5 file:text-xs file:font-semibold file:text-amber-950">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full text-sm transition">
                        {{ __('borrower.payments_page.show.upload_proof_button') }}
                    </button>
                </form>
            </div>
        @endif
    </div>

</x-site.borrower-layout>

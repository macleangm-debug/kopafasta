<x-site.borrower-layout :title="brand_title($payment->reference)" active="payments" content-width="wide">

    <div class="mb-4">
        <a href="{{ route('site.borrower.payments') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('borrower.payments_page.back_history') }}</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="glass-card overflow-hidden">
        <div class="bg-gradient-to-br from-brand to-brand-light px-6 py-5 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_right,_#f5c842,_transparent_50%)]"></div>
            <div class="relative">
            <p class="text-[10px] uppercase tracking-widest text-white/70">{{ __('borrower.payments_page.show.payment_reference') }}</p>
            <p class="text-2xl font-bold font-mono mt-1">{{ $payment->reference }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @php
                    $badge = match ($payment->status) {
                        'verified', 'paid' => 'bg-emerald-500/20 text-emerald-100 ring-emerald-400/40',
                        'rejected' => 'bg-red-500/20 text-red-100 ring-red-400/40',
                        'clarification_requested' => 'bg-sky-500/20 text-sky-100 ring-sky-400/40',
                        default => 'bg-amber-500/20 text-amber-100 ring-amber-400/40',
                    };
                @endphp
                <span class="rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">{{ $payment->statusLabel() }}</span>
            </div>
            </div>
        </div>

        <div class="p-6 grid sm:grid-cols-2 gap-5 text-sm">
            <div>
                <p class="text-xs text-gray-500 uppercase">{{ __('borrower.payments_page.show.type') }}</p>
                <p class="font-medium mt-0.5">{{ $payment->typeLabel() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">{{ __('borrower.payments_page.show.method') }}</p>
                <p class="font-medium mt-0.5">{{ $payment->methodLabel() }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">{{ __('borrower.payments_page.show.amount') }}</p>
                <p class="font-semibold mt-0.5 text-lg">{{ format_money($payment->amount) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 uppercase">{{ __('borrower.payments_page.show.date') }}</p>
                <p class="font-medium mt-0.5">{{ $payment->created_at?->format('d M Y') }}</p>
            </div>
            @if ($payment->mobile_number)
                <div>
                    <p class="text-xs text-gray-500 uppercase">{{ __('borrower.payments_page.show.mobile_number') }}</p>
                    <p class="font-mono mt-0.5">{{ $payment->mobile_number }}</p>
                </div>
            @endif
            @if ($mobileDetails && $mobileDetails['number'])
                <div class="sm:col-span-2 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4">
                    <p class="text-xs uppercase tracking-widest text-sky-700 mb-2">{{ __('borrower.payments_page.show.mobile_details') }}</p>
                    <dl class="grid sm:grid-cols-2 gap-2 text-xs">
                        <div><dt class="text-sky-700">{{ __('borrower.payments_page.show.provider') }}</dt><dd class="font-medium">{{ $mobileDetails['provider'] }}</dd></div>
                        <div><dt class="text-sky-700">{{ __('borrower.payments_page.show.number') }}</dt><dd class="font-mono font-medium">{{ $mobileDetails['number'] }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sky-700">{{ __('borrower.payments_page.show.instructions') }}</dt><dd class="font-medium">{{ $mobileDetails['instructions'] }}</dd></div>
                    </dl>
                </div>
            @endif
            @if ($bankDetails)
                <div class="sm:col-span-2 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-4">
                    <p class="text-xs uppercase tracking-widest text-sky-700 mb-2">{{ __('borrower.payments_page.show.bank_details') }}</p>
                    <dl class="grid sm:grid-cols-2 gap-2 text-xs">
                        <div><dt class="text-sky-700">{{ __('borrower.payments_page.create.bank_label') }}</dt><dd class="font-medium">{{ $bankDetails['bank_name'] }}</dd></div>
                        <div><dt class="text-sky-700">{{ __('borrower.payments_page.create.account_name') }}</dt><dd class="font-medium">{{ $bankDetails['account_name'] }}</dd></div>
                        <div><dt class="text-sky-700">{{ __('borrower.payments_page.create.account_number') }}</dt><dd class="font-mono font-medium">{{ $bankDetails['account_number'] }}</dd></div>
                        <div><dt class="text-sky-700">{{ __('borrower.payments_page.show.reference') }}</dt><dd class="font-mono font-medium">{{ $payment->reference }}</dd></div>
                    </dl>
                </div>
            @endif
            <div class="sm:col-span-2">
                <p class="text-xs text-gray-500 uppercase mb-1">{{ __('borrower.payments_page.show.proof_submitted') }}</p>
                @if ($payment->hasProof())
                    <p class="text-sm text-gray-700">{{ $payment->proof_original_name ?? __('borrower.payments_page.show.document_uploaded') }}</p>
                @else
                    <p class="text-sm text-gray-500">{{ __('borrower.payments_page.show.no_proof') }}</p>
                @endif
            </div>
            @if ($payment->verification_notes)
                <div class="sm:col-span-2 rounded-xl bg-gray-50 px-4 py-3">
                    <p class="text-xs text-gray-500 uppercase mb-1">{{ __('borrower.payments_page.show.verification_notes') }}</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $payment->verification_notes }}</p>
                </div>
            @endif
        </div>

        @if ($payment->payment_method === 'bank_transfer' && $payment->isPending())
            <div class="px-6 pb-6 border-t border-gray-100 pt-5">
                <h3 class="text-sm font-semibold mb-2">{{ __('borrower.payments_page.show.upload_proof_heading') }}</h3>
                <form method="POST" action="{{ route('site.borrower.payments.proof', $payment) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <input type="file" name="proof" required accept=".jpg,.jpeg,.png,.pdf"
                           class="text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-2 file:text-xs file:font-semibold">
                    <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-4 py-2 rounded-full text-sm">
                        {{ __('borrower.payments_page.show.upload_proof_button') }}
                    </button>
                </form>
            </div>
        @endif
    </div>

</x-site.borrower-layout>

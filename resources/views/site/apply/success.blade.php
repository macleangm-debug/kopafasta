<x-site.layout title="Application received — Kopafasta">
    <section class="max-w-xl mx-auto px-4 py-20 text-center">
        <div class="size-16 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center mx-auto mb-5">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-3xl font-bold tracking-tight">Application received</h1>
        <p class="mt-2 text-gray-600">Your reference is <span class="font-mono font-bold text-gray-900">{{ $application->application_number }}</span>. We'll text and email you as soon as the review starts.</p>

        <div class="mt-8 bg-white rounded-2xl border border-gray-200 p-6 text-left">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="text-gray-500">Product</div><div class="font-medium">{{ $application->product->name }} ({{ $application->product->code }})</div>
                <div class="text-gray-500">Amount</div><div class="font-medium">TZS {{ number_format($application->requested_amount, 0) }}</div>
                <div class="text-gray-500">Tenure</div><div class="font-medium">{{ $application->requested_tenure_months }} months</div>
                <div class="text-gray-500">Status</div><div class="font-medium capitalize">{{ str_replace('_',' ',$application->status) }}</div>
                <div class="text-gray-500">Registration fee</div><div class="font-medium">TZS {{ number_format((int) $application->registration_fee_amount, 0) }} <span class="text-xs text-emerald-600 capitalize">· {{ $application->registration_fee_status }}</span></div>
                @if ((int) $application->application_fee_amount > 0)
                    <div class="text-gray-500">Application fee</div><div class="font-medium">TZS {{ number_format((int) $application->application_fee_amount, 0) }} <span class="text-xs text-gray-500 capitalize">· {{ $application->application_fee_status }}</span></div>
                @endif
            </div>
        </div>

        <div class="mt-8 flex gap-3 justify-center flex-wrap">
            <a href="{{ route('site.borrower.application', $application->id) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full">Upload required documents →</a>
            <a href="{{ route('site.borrower.dashboard') }}" class="border border-gray-300 hover:border-gray-900 text-gray-900 font-semibold px-5 py-2.5 rounded-full">Go to dashboard</a>
        </div>
    </section>
</x-site.layout>

<x-site.borrower-layout title="Application received — Kopafasta" active="applications">
    <div class="max-w-xl mx-auto text-center py-6">
        <div class="size-16 rounded-full bg-emerald-100 text-emerald-600 grid place-items-center mx-auto mb-5">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
        </div>
        <h1 class="text-3xl font-bold tracking-tight">Application received</h1>
        <p class="mt-2 text-gray-600">Reference <span class="font-mono font-bold text-gray-900">{{ $application->application_number }}</span>. We'll notify you when review starts.</p>

        <div class="mt-8 bg-white rounded-2xl border border-gray-200 p-6 text-left">
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="text-gray-500">Product</div><div class="font-medium">{{ $application->product->name }}</div>
                <div class="text-gray-500">Amount</div><div class="font-medium">TZS {{ number_format($application->requested_amount, 0) }}</div>
                <div class="text-gray-500">Tenure</div><div class="font-medium">{{ $application->requested_tenure_months }} months</div>
                <div class="text-gray-500">Status</div><div class="font-medium capitalize">{{ str_replace('_',' ',$application->status) }}</div>
            </div>
        </div>

        <div class="mt-8 flex gap-3 justify-center flex-wrap">
            <a href="{{ route('site.borrower.application', $application->id) }}" class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-5 py-2.5 rounded-full">Upload documents →</a>
            <a href="{{ route('site.borrower.dashboard') }}" class="border border-gray-300 text-gray-900 font-semibold px-5 py-2.5 rounded-full">Dashboard</a>
        </div>
    </div>
</x-site.borrower-layout>

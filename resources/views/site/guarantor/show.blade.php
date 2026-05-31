<x-site.layout title="Guarantor invitation — Kopafasta">

    <div class="max-w-xl mx-auto px-4 py-12">
        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 p-6 sm:p-8">
            <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">Guarantor request</p>
            <h1 class="text-2xl font-bold mb-2">Guarantee a loan application</h1>
            <p class="text-sm text-gray-600 mb-6">
                <strong>{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</strong>
                has requested you to act as guarantor
                @if ($invitation->application)
                    for a <strong>{{ $invitation->application->product->name ?? 'loan' }}</strong>
                    of TZS {{ number_format((float) $invitation->application->requested_amount) }}.
                @endif
            </p>

            @if ($invitation->type === 'external')
                <p class="text-sm text-gray-600 mb-6">If you accept, you will need to complete membership registration and KYC before the application can proceed.</p>
            @endif

            <div class="flex flex-wrap gap-3">
                <form method="POST" action="{{ route('site.guarantor.accept', $invitation->token) }}">
                    @csrf
                    <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-5 py-2.5 rounded-full text-sm">Accept</button>
                </form>
                <form method="POST" action="{{ route('site.guarantor.reject', $invitation->token) }}" class="flex items-end gap-2">
                    @csrf
                    <button class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-5 py-2.5 rounded-full text-sm">Decline</button>
                </form>
            </div>
        </div>
    </div>

</x-site.layout>

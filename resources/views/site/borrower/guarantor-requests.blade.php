<x-site.borrower-layout title="Guarantor requests — Kopafasta" active="guarantors">

    <div class="max-w-3xl">
        <h1 class="text-2xl font-bold mb-1">Guarantor requests</h1>
        <p class="text-sm text-gray-500 mb-6">Other members have asked you to guarantee their loan applications.</p>

        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="space-y-4">
            @forelse ($requests as $invitation)
                @php $link = $invitation->customerGuarantor; @endphp
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <p class="font-semibold">{{ trim($invitation->borrower->first_name.' '.$invitation->borrower->last_name) }}</p>
                    <p class="text-sm text-gray-600 mt-1">
                        @if ($invitation->application)
                            {{ $invitation->application->product->name ?? 'Loan' }} · TZS {{ number_format((float) $invitation->application->requested_amount) }}
                        @endif
                    </p>
                    @if ($link)
                        <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}" class="mt-4 flex flex-wrap gap-3 items-end">
                            @csrf
                            <input type="hidden" name="action" value="approve">
                            <button class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-full text-sm">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('site.borrower.guarantor-requests.respond', $link) }}" class="mt-2 flex flex-wrap gap-3 items-end">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <input name="notes" placeholder="Optional reason" class="rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2 text-sm flex-1 min-w-[200px]">
                            <button class="bg-white ring-1 ring-gray-200 hover:bg-gray-50 text-gray-700 font-semibold px-4 py-2 rounded-full text-sm">Decline</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-sm text-gray-500">
                    No pending guarantor requests.
                </div>
            @endforelse
        </div>
    </div>

</x-site.borrower-layout>

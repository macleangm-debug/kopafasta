<x-admin.layout title="Write off loan" heading="Write off loan" :subheading="$loan->loan_number">

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.loans.write-off', $loan) }}" class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4 max-w-xl"
          onsubmit="return confirm('Write off {{ $loan->loan_number }}? This posts to the General Ledger and marks the loan written_off.');">
        @csrf

        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="text-gray-500">Customer</div>
                <div class="font-medium">{{ optional($loan->customer)->first_name }} {{ optional($loan->customer)->last_name }}</div>
            </div>
            <div>
                <div class="text-gray-500">Outstanding balance</div>
                <div class="font-medium">{{ format_number((float) $loan->outstanding_balance) }} {{ $loan->currency ?? 'TZS' }}</div>
            </div>
            <div>
                <div class="text-gray-500">Status</div>
                <div class="font-medium">{{ ucfirst($loan->status) }}</div>
            </div>
            <div>
                <div class="text-gray-500">Disbursed</div>
                <div class="font-medium">{{ optional($loan->disbursement_date)->toDateString() ?? '—' }}</div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Amount to write off (TZS)</label>
            <input type="number" step="0.01" name="amount" value="{{ old('amount', (float) $loan->outstanding_balance) }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
            <p class="text-xs text-gray-500 mt-1">Defaults to the full outstanding balance.</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
            <textarea name="reason" rows="3" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('reason') }}</textarea>
        </div>

        <div class="pt-4 border-t border-gray-100 flex items-center gap-3">
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Write off loan</button>
            <a href="{{ route('admin.loans.show', $loan) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
        </div>
    </form>
</x-admin.layout>

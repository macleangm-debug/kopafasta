<x-admin.layout title="Write off loan" heading="Write off loan" :subheading="$loan->loan_number">

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @php
        $writeOffService = app(\App\Services\WriteOffRequestService::class);
        $canRecommend = $writeOffService->canRecommend(auth()->user()) && ! $writeOffService->hasOpenRequest($loan);
    @endphp

    @if (! empty($approvalRequired))
        <div class="mb-4 rounded-lg bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
            Write-off approval is required. Recommend write-off below — manager and finance must approve before execution.
        </div>
    @endif

    <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4 max-w-xl">
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

        @if (! empty($approvalRequired) && $canRecommend)
            <form method="POST" action="{{ route('admin.loans.write-off-requests.store', $loan) }}" class="space-y-4 pt-4 border-t border-gray-100">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount to write off (TZS)</label>
                    <input type="number" step="0.01" name="amount" value="{{ old('amount', (float) $loan->outstanding_balance) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" rows="3" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">{{ old('reason') }}</textarea>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Recommend write-off</button>
                    <a href="{{ route('admin.write-off-requests.index') }}" class="text-sm text-brand hover:underline">View queue</a>
                </div>
            </form>
        @elseif (! empty($approvalRequired))
            <p class="text-sm text-gray-600 pt-4 border-t border-gray-100">
                This loan already has a pending write-off request or you are not authorized to recommend write-offs.
                <a href="{{ route('admin.write-off-requests.index') }}" class="text-amber-700 font-semibold hover:underline">View write-off queue</a>
            </p>
        @else
            <form method="POST" action="{{ route('admin.loans.write-off', $loan) }}" class="space-y-4 pt-4 border-t border-gray-100"
                  onsubmit="return confirm('Write off {{ $loan->loan_number }}? This posts to the General Ledger and marks the loan written_off.');">
                @csrf
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
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-4 py-2 rounded-lg text-sm">Write off loan</button>
                    <a href="{{ route('admin.loans.show', $loan) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
                </div>
            </form>
        @endif

        @if (empty($approvalRequired))
            <div class="pt-2">
                <a href="{{ route('admin.loans.show', $loan) }}" class="text-sm text-gray-600 hover:underline">Cancel</a>
            </div>
        @endif
    </div>
</x-admin.layout>

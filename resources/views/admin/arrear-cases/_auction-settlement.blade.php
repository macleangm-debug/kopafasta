@if ($loan && in_array($arrearCase->status, ['open', 'escalated']))
    @php
        $balanceBreakdown = app(\App\Services\LoanBalanceService::class)->breakdown($loan);
    @endphp
    <div class="bg-white rounded-xl ring-1 ring-indigo-200 p-6">
        <h2 class="text-sm font-semibold text-brand mb-1">Asset auction settlement</h2>
        <p class="text-xs text-gray-500 mb-4">Apply proceeds: outstanding → recovery costs → surplus returned to borrower. Shortfall keeps the loan active.</p>

        <dl class="grid grid-cols-2 gap-3 text-sm mb-4">
            <div class="rounded-lg bg-gray-50 px-3 py-2">
                <dt class="text-[10px] uppercase text-gray-500">Outstanding</dt>
                <dd class="font-bold">{{ format_money($balanceBreakdown['total_outstanding']) }}</dd>
            </div>
            <div class="rounded-lg bg-gray-50 px-3 py-2">
                <dt class="text-[10px] uppercase text-gray-500">Recovery costs</dt>
                <dd class="font-bold">{{ format_money($balanceBreakdown['recovery_costs']) }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('admin.arrear-cases.auction-settle', $arrearCase) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Auction proceeds</label>
                <input type="number" step="0.01" min="0.01" name="auction_proceeds" required
                       value="{{ old('auction_proceeds') }}"
                       class="w-full rounded-lg border-gray-300 text-sm" placeholder="Amount received at auction">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                <textarea name="notes" rows="2" maxlength="2000" class="w-full rounded-lg border-gray-300 text-sm">{{ old('notes') }}</textarea>
            </div>
            <button type="button" class="w-full text-sm font-semibold text-white bg-brand hover:bg-brand-light px-3 py-2 rounded-lg"
                    @click.prevent="window.confirmAction({
                        title: @js('Record auction settlement?'),
                        message: @js('Record this auction settlement? This posts repayments and updates the loan balance.'),
                        confirmLabel: @js('Record settlement'),
                        tone: 'confirm',
                        confirmClass: 'bg-brand hover:bg-brand-light text-white',
                        onConfirm: () => $el.closest('form').submit(),
                    })">
                Record auction settlement
            </button>
        </form>

        @if ($loan->assetAuctionSettlements?->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold uppercase text-gray-500 mb-2">Previous settlements</p>
                <ul class="space-y-2 text-xs">
                    @foreach ($loan->assetAuctionSettlements as $settlement)
                        <li class="rounded-lg bg-gray-50 px-3 py-2">
                            {{ optional($settlement->settled_at)->format('d M Y') }} ·
                            Proceeds {{ format_money($settlement->auction_proceeds) }} ·
                            Refund {{ format_money($settlement->borrower_refund) }} ·
                            Balance {{ format_money($settlement->remaining_balance) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

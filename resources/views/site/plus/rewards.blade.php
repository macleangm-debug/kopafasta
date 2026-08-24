<x-site.borrower-layout :title="brand_title(__('plus.home.rewards'))" active="dashboard">
    <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4 sm:p-6 mb-4">
        <p class="text-sm text-gray-600">{{ __('plus.rewards.balance') }}</p>
        <p class="text-2xl font-semibold">{{ __('plus.rewards.points', ['balance' => $balance]) }}</p>
        <p class="text-sm text-gray-500 mt-2">{{ __('plus.rewards.hint') }}</p>
        @if ($balance > 0)
            <form method="post" action="{{ route('site.borrower.plus.rewards.redeem') }}" class="mt-4 flex flex-col sm:flex-row gap-2">
                @csrf
                <input type="number" name="points" min="1" max="{{ $balance }}" inputmode="numeric" class="min-h-11 rounded-xl border-gray-300" placeholder="{{ __('plus.rewards.placeholder_points') }}">
                <input name="reason" required class="flex-1 min-h-11 rounded-xl border-gray-300" placeholder="{{ __('plus.rewards.placeholder_reason') }}">
                <button class="rounded-xl bg-brand text-white px-4 py-3 text-sm font-semibold">{{ __('plus.rewards.redeem') }}</button>
            </form>
        @endif
    </div>
    <div class="space-y-2">
        @foreach ($ledger as $row)
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3">
                <span>{{ $row->reason }}</span>
                <span>{{ $row->points > 0 ? '+' : '' }}{{ $row->points }}</span>
            </div>
        @endforeach
    </div>
</x-site.borrower-layout>

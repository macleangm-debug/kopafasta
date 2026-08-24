<x-site.borrower-layout :title="brand_title(__('plus.home.money'))" active="dashboard">
    <form method="post" action="{{ route('site.borrower.plus.money.save') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 sm:p-6 space-y-4">
        @csrf
        <h1 class="text-xl font-semibold">{{ __('plus.money.title') }}</h1>
        <label class="block text-sm">{{ __('plus.money.in') }}
            <input name="in_amount" type="number" step="0.01" inputmode="decimal" class="mt-1 w-full min-h-11 rounded-xl border-gray-300" value="0">
        </label>
        <label class="block text-sm">{{ __('plus.money.out') }}
            <input name="out_amount" type="number" step="0.01" inputmode="decimal" class="mt-1 w-full min-h-11 rounded-xl border-gray-300" value="0">
        </label>
        <button class="w-full sm:w-auto rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.money.save') }}</button>
    </form>
    <div class="mt-6 space-y-2">
        @foreach ($entries as $entry)
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3">
                <span>{{ $entry->entry_date?->format('d M') }}</span>
                <span>{{ __('plus.money.left', ['amount' => format_money((float) ($entry->inflow ?? $entry->money_in) - (float) ($entry->outflow ?? $entry->money_out))]) }}</span>
            </div>
        @endforeach
    </div>
</x-site.borrower-layout>

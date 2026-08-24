<x-site.borrower-layout :title="brand_title(__('plus.home.business'))" active="dashboard">
    <form method="post" action="{{ route('site.borrower.plus.business.save') }}" class="rounded-2xl bg-white ring-1 ring-gray-200 p-4 sm:p-6 space-y-4">
        @csrf
        <h1 class="text-xl font-semibold">{{ __('plus.business.title') }}</h1>
        <p class="text-sm text-gray-600">{{ __('plus.business.summary', ['sold' => format_money($sold), 'spent' => format_money($spent), 'left' => format_money($sold - $spent)]) }}</p>
        <label class="block text-sm">{{ __('plus.business.sold') }}
            <input name="sold" type="number" step="0.01" inputmode="decimal" class="mt-1 w-full min-h-11 rounded-xl border-gray-300" value="0">
        </label>
        <label class="block text-sm">{{ __('plus.business.spent') }}
            <input name="spent" type="number" step="0.01" inputmode="decimal" class="mt-1 w-full min-h-11 rounded-xl border-gray-300" value="0">
        </label>
        <button class="w-full sm:w-auto rounded-xl bg-brand text-white px-5 py-3 font-semibold">{{ __('plus.business.save') }}</button>
    </form>
    <div class="mt-6 space-y-2">
        @foreach ($entries as $entry)
            <div class="rounded-xl bg-white ring-1 ring-gray-100 px-4 py-3 text-sm flex justify-between gap-3">
                <span>{{ $entry->entry_date?->format('d M') }}</span>
                <span>{{ __('plus.business.left', ['amount' => format_money((float) $entry->sold - (float) $entry->spent)]) }}</span>
            </div>
        @endforeach
    </div>
</x-site.borrower-layout>

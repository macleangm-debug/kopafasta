<x-admin.layout
    :title="'Adjust capital — '.$record->name"
    :heading="'Adjust capital'"
    :subheading="$record->name"
    :back-url="route('admin.lenders.show', $record)"
    back-label="Partner">

    <div class="mb-6 rounded-xl bg-amber-50 ring-1 ring-amber-200 px-4 py-3 text-sm text-amber-900">
        {{ __('admin.capital_partner.available_hint') }}
        Available: <strong>{{ format_money($metrics['capital_available']) }}</strong>
    </div>

    <form method="post" action="{{ route('admin.lenders.adjust-capital.store', $record) }}" class="max-w-lg space-y-6">
        @csrf
        <div class="bg-white rounded-xl ring-1 ring-gray-200 p-6 space-y-4">
            <x-admin.select name="direction" label="Action" :options="['increase' => __('admin.capital_partner.increase'), 'decrease' => __('admin.capital_partner.decrease')]" value="increase" required />
            <x-admin.input name="amount" label="Amount (TZS)" required money />
            <x-admin.textarea name="notes" label="Notes" rows="3" />
        </div>
        <button type="submit" class="text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2.5 rounded-lg">Save</button>
    </form>
</x-admin.layout>

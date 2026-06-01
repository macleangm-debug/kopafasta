<x-admin.review-section id="customer-residence" title="Residence" subtitle="Address on file for verification and collections">
    <form method="POST" action="{{ route('admin.customers.section.update', [$customer, 'residence']) }}" class="grid md:grid-cols-2 gap-4">
        @csrf
        @method('PUT')
        <x-admin.select name="region" label="Region" :options="collect($regions)->mapWithKeys(fn ($r) => [$r => $r])->all()" :value="$customer->region" placeholder="— Select region —" />
        <x-admin.input name="district" label="District" :value="$customer->district" />
        <x-admin.input name="ward" label="Ward" :value="$customer->ward" />
        <x-admin.input name="street" label="Street / plot / house no." :value="$customer->street" />
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="inline-flex text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg">Save residence</button>
        </div>
    </form>
</x-admin.review-section>

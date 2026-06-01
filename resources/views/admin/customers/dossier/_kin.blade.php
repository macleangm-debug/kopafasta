<x-admin.review-section id="customer-kin" title="Next of kin" subtitle="Emergency contact required for most loan products">
    <form method="POST" action="{{ route('admin.customers.section.update', [$customer, 'kin']) }}" class="grid md:grid-cols-2 gap-4">
        @csrf
        @method('PUT')
        <x-admin.input name="nok_name" label="Full name" :value="$customer->nok_name" />
        <x-admin.input name="nok_relationship" label="Relationship" :value="$customer->nok_relationship" placeholder="e.g. Spouse, Parent" />
        <x-admin.input name="nok_phone" label="Phone" :value="$customer->nok_phone" />
        <x-admin.select name="nok_region" label="Region" :options="collect($regions)->mapWithKeys(fn ($r) => [$r => $r])->all()" :value="$customer->nok_region" placeholder="— Select —" />
        <x-admin.input name="nok_district" label="District" :value="$customer->nok_district" />
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="inline-flex text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 px-5 py-2 rounded-lg">Save next of kin</button>
        </div>
    </form>
</x-admin.review-section>

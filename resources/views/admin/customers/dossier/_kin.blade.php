<x-admin.dossier-section id="customer-kin" title="Next of kin" subtitle="Emergency contact required for most loan products">
    <x-slot:view>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Full name</dt><dd class="font-medium mt-0.5">{{ $customer->nok_name ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Relationship</dt><dd class="font-medium mt-0.5">{{ kin_relationship_label($customer->nok_relationship) ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->nok_phone ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Region</dt><dd class="font-medium mt-0.5">{{ $customer->nok_region ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">District</dt><dd class="font-medium mt-0.5">{{ $customer->nok_district ?: '—' }}</dd></div>
        </dl>
    </x-slot:view>
    <x-slot:edit>
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
    </x-slot:edit>
</x-admin.dossier-section>

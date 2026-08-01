<x-admin.dossier-section id="customer-residence" title="Residence" subtitle="Address on file for verification and collections">
    <x-slot:view>
        <dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Region</dt><dd class="font-medium mt-0.5">{{ $customer->region ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">District</dt><dd class="font-medium mt-0.5">{{ $customer->district ?: '—' }}</dd></div>
            <div><dt class="text-xs uppercase tracking-wider text-gray-500">Ward</dt><dd class="font-medium mt-0.5">{{ $customer->ward ?: '—' }}</dd></div>
            <div class="md:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Street / plot / house no.</dt><dd class="font-medium mt-0.5">{{ $customer->street ?: '—' }}</dd></div>
        </dl>
    </x-slot:view>
    <x-slot:edit>
        <form method="POST" action="{{ route('admin.customers.section.update', [$customer, 'residence']) }}" class="grid md:grid-cols-2 gap-4">
            @csrf
            @method('PUT')
            <x-admin.select name="region" label="Region" :options="collect($regions)->mapWithKeys(fn ($r) => [$r => $r])->all()" :value="$customer->region" placeholder="— Select region —" />
            <x-admin.input name="district" label="District" :value="$customer->district" />
            <x-admin.input name="ward" label="Ward" :value="$customer->ward" />
            <x-admin.input name="street" label="Street / plot / house no." :value="$customer->street" />
            <div class="md:col-span-2 flex justify-end">
                <button type="submit" class="inline-flex text-sm font-semibold text-brand bg-brand-gold hover:brightness-95 px-5 py-2 rounded-lg">Save residence</button>
            </div>
        </form>
    </x-slot:edit>
</x-admin.dossier-section>

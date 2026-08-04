<dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Full name</dt><dd class="font-medium mt-0.5">{{ $customer->nok_name ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Relationship</dt><dd class="font-medium mt-0.5">{{ kin_relationship_label($customer->nok_relationship) ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Phone</dt><dd class="font-medium mt-0.5">{{ $customer->nok_phone ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Region</dt><dd class="font-medium mt-0.5">{{ $customer->nok_region ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">District</dt><dd class="font-medium mt-0.5">{{ $customer->nok_district ?: '—' }}</dd></div>
</dl>

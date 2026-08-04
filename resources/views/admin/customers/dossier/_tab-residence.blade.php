<dl class="grid md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Region</dt><dd class="font-medium mt-0.5">{{ $customer->region ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">District</dt><dd class="font-medium mt-0.5">{{ $customer->district ?: '—' }}</dd></div>
    <div><dt class="text-xs uppercase tracking-wider text-gray-500">Ward</dt><dd class="font-medium mt-0.5">{{ $customer->ward ?: '—' }}</dd></div>
    <div class="md:col-span-2"><dt class="text-xs uppercase tracking-wider text-gray-500">Street / plot / house no.</dt><dd class="font-medium mt-0.5">{{ $customer->street ?: '—' }}</dd></div>
</dl>

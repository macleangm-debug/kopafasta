@php($r = $record ?? null)
<x-admin.step title="Campaign">
    <x-admin.input name="code" label="Code" :value="$r?->code" required placeholder="BDAY-2026" />
    <x-admin.input name="name" label="Name" :value="$r?->name" required />
    <x-admin.select name="type" label="Type" :options="$types" :value="$r?->type" required />
    <x-admin.select name="status" label="Status" :options="$statuses" :value="$r?->status ?? 'draft'" required />
    <x-admin.select name="applies_to" label="Applies to" :options="$appliesTo" :value="$r?->applies_to" placeholder="— Optional —" />
    <p class="md:col-span-2 text-xs text-gray-500 -mt-2">Promotions apply to fees only — not loan interest or penalty charges.</p>
    <x-admin.input name="discount_percent" label="Discount (%)" type="number" step="0.01" :value="$r?->discount_percent" />
    <x-admin.input name="discount_amount" label="Discount amount (TZS)" money :decimals="2" :value="$r?->discount_amount" />
    <x-admin.input name="starts_at" label="Starts" type="date" :value="optional($r?->starts_at)->format('Y-m-d')" />
    <x-admin.input name="ends_at" label="Ends" type="date" :value="optional($r?->ends_at)->format('Y-m-d')" />
    <div class="md:col-span-2">
        <x-admin.textarea name="message_template" label="Message template" :value="$r?->message_template" rows="4" placeholder="Happy birthday, :name! …" />
        <p class="text-xs text-gray-500 mt-1">Use <code>:name</code> or <code>:first_name</code> for personalization.</p>
    </div>
</x-admin.step>

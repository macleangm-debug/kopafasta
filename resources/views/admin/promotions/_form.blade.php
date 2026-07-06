@php($r = $record ?? null)
<x-admin.step title="Campaign">
    <x-admin.input name="code" label="Code" :value="$r?->code" required placeholder="MEM-2026" />
    <x-admin.input name="name" label="Campaign name" :value="$r?->name" required />
    <x-admin.select name="type" label="Type" :options="$types" :value="$r?->type" required />
    <x-admin.select name="status" label="Status" :options="$statuses" :value="$r?->status ?? 'draft'" required />
    <x-admin.select name="applies_to" label="Applies to" :options="$appliesTo" :value="$r?->applies_to" placeholder="— Optional —" />
    <x-admin.select name="eligible_members" label="Eligible members" :options="$eligibleMembers ?? []" :value="$r?->eligible_members ?? 'all'" />
    <p class="md:col-span-2 text-xs text-gray-500 -mt-2">Promotions apply to fees only — not loan interest or penalty charges.</p>
    <x-admin.input name="starts_at" label="Start date" type="date" :value="optional($r?->starts_at)->format('Y-m-d')" />
    <x-admin.input name="ends_at" label="End date" type="date" :value="optional($r?->ends_at)->format('Y-m-d')" />
    <x-admin.input name="original_fee" label="Original membership fee (TZS)" money :decimals="2" :value="$r?->original_fee" />
    <x-admin.select name="discount_type" label="Discount type" :options="$discountTypes ?? []" :value="$r?->discount_type ?? 'percentage'" />
    <x-admin.input name="discount_percent" label="Promotional discount (%)" type="number" step="0.01" :value="$r?->discount_percent" />
    <x-admin.input name="discount_amount" label="Promotional fee / fixed discount (TZS)" money :decimals="2" :value="$r?->discount_amount" />
    <x-admin.input name="banner_path" label="Campaign banner path or URL" :value="$r?->banner_path" placeholder="/storage/campaigns/summer.jpg" />
    <div class="md:col-span-2">
        <x-admin.textarea name="message_en" label="Campaign message (English)" :value="$r?->message_en ?? $r?->message_template" rows="4" />
    </div>
    <div class="md:col-span-2">
        <x-admin.textarea name="message_sw" label="Campaign message (Swahili)" :value="$r?->message_sw" rows="4" />
    </div>
    <div class="md:col-span-2">
        <x-admin.textarea name="message_template" label="Legacy message template" :value="$r?->message_template" rows="3" placeholder="Optional fallback" />
        <p class="text-xs text-gray-500 mt-1">Use <code>:name</code> or <code>:first_name</code> for personalization.</p>
    </div>
</x-admin.step>

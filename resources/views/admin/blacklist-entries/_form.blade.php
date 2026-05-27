@php($r = $record ?? null)
<x-admin.step title="Blacklist entry">
    <x-admin.select name="identifier_type"  label="Identifier type"  :options="$types" :value="$r?->identifier_type" required placeholder="—" />
    <x-admin.input  name="identifier_value" label="Identifier value" :value="$r?->identifier_value" required />
    <x-admin.input  name="reason"  label="Reason"  :value="$r?->reason" required />
    <x-admin.select name="source"  label="Source"  :options="$sources" :value="$r?->source ?? 'internal'" required />
    <x-admin.input  name="listed_on"  label="Listed on"  type="date" :value="optional($r?->listed_on)->format('Y-m-d')" />
    <x-admin.input  name="expires_on" label="Expires on" type="date" :value="optional($r?->expires_on)->format('Y-m-d')" />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Listed','0'=>'Cleared']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="notes" label="Notes" :value="$r?->notes" rows="2" />
    </div>
</x-admin.step>

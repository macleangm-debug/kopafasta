@php($r = $record ?? null)
<x-admin.step title="PEP flag">
    <x-admin.select name="customer_id" label="Link to customer (optional)" :options="$customers" :value="$r?->customer_id" placeholder="—" />
    <x-admin.input  name="full_name"   label="Full name"    :value="$r?->full_name" required />
    <x-admin.input  name="position"    label="Position"     :value="$r?->position" />
    <x-admin.input  name="organization" label="Organization" :value="$r?->organization" />
    <x-admin.select name="category"   label="Category"    :options="$categories"  :value="$r?->category ?? 'domestic'" required />
    <x-admin.select name="risk_level" label="Risk level"  :options="$risk_levels" :value="$r?->risk_level ?? 'high'" required />
    <x-admin.input  name="listed_on"  label="Listed on"   type="date" :value="optional($r?->listed_on)->format('Y-m-d')" />
    <x-admin.select name="is_active"  label="Status"      :options="['1'=>'Flagged','0'=>'Cleared']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="notes" label="Notes" :value="$r?->notes" rows="3" />
    </div>
</x-admin.step>

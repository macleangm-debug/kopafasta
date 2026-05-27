@php($r = $record ?? null)
<x-admin.step title="Write-off rule">
    <x-admin.input  name="name" label="Name" :value="$r?->name" required />
    <x-admin.input  name="days_past_due" label="Days past due ≥" type="number" :value="$r?->days_past_due ?? '180'" required />
    <x-admin.input  name="min_outstanding" label="Min outstanding" type="number" step="0.01" :value="$r?->min_outstanding" />
    <x-admin.input  name="max_outstanding" label="Max outstanding" type="number" step="0.01" :value="$r?->max_outstanding" />
    <x-admin.select name="require_committee_approval" label="Require committee approval" :options="['1'=>'Yes','0'=>'No']" :value="(string)($r?->require_committee_approval ?? '1')" />
    <x-admin.select name="auto_propose" label="Auto propose write-off" :options="['1'=>'Yes','0'=>'No']" :value="(string)($r?->auto_propose ?? '0')" />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
</x-admin.step>

@php($r = $record ?? null)
<x-admin.step title="AML rule">
    <x-admin.input  name="code"      label="Code" :value="$r?->code" required />
    <x-admin.input  name="name"      label="Name" :value="$r?->name" required />
    <x-admin.select name="rule_type" label="Rule type" :options="$rule_types" :value="$r?->rule_type" required placeholder="—" />
    <x-admin.input  name="threshold_amount" label="Threshold amount" type="number" step="0.01" :value="$r?->threshold_amount" />
    <x-admin.input  name="threshold_count"  label="Threshold count"  type="number" :value="$r?->threshold_count" />
    <x-admin.input  name="window_days"      label="Window (days)"    type="number" :value="$r?->window_days" />
    <x-admin.select name="action"    label="Action" :options="$actions" :value="$r?->action ?? 'flag'" required />
    <x-admin.select name="severity"  label="Severity" :options="$severities" :value="$r?->severity ?? 'medium'" required />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
</x-admin.step>

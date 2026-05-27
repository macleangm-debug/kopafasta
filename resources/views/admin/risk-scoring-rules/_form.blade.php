@php($r = $record ?? null)
<x-admin.step title="Scoring rule">
    <x-admin.input  name="factor"   label="Factor (column / metric)" :value="$r?->factor" required placeholder="age, monthly_income, employment_type…" />
    <x-admin.select name="operator" label="Operator" :options="$operators" :value="$r?->operator" required placeholder="—" />
    <x-admin.input  name="value"    label="Value" :value="$r?->value" required />
    <x-admin.input  name="weight"   label="Weight (points)" type="number" :value="$r?->weight ?? '0'" required />
    <x-admin.select name="category" label="Category" :options="$categories" :value="$r?->category ?? 'financial'" required />
    <x-admin.select name="is_active" label="Status"  :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
</x-admin.step>

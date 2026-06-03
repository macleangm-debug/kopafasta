@php($r = $record ?? null)
<x-admin.step title="Fee definition">
    <x-admin.input  name="code"  label="Code" :value="$r?->code" required />
    <x-admin.input  name="name"  label="Name" :value="$r?->name" required />
    <x-admin.select name="type"  label="Type"  :options="$types" :value="$r?->type" required placeholder="—" />
    <x-admin.select name="basis" label="Basis" :options="$bases" :value="$r?->basis" required placeholder="—"
                    help="Fixed = TZS amount. Percentage = % of approved principal (or overdue balance for late fees)." />
    <x-admin.input  name="amount" label="Amount / rate" type="number" step="0.0001" :value="$r?->amount ?? '0'" required
                    help="For percentage, enter 2 for 2%. For fixed, enter TZS amount." />
    <x-admin.input  name="min_amount" label="Min amount" type="number" step="0.01" :value="$r?->min_amount" />
    <x-admin.input  name="max_amount" label="Max amount" type="number" step="0.01" :value="$r?->max_amount" />
    <x-admin.select name="charge_when" label="When"   :options="$whens" :value="$r?->charge_when ?? 'disbursement'" required />
    <x-admin.select name="gl_account_id" label="GL account (income)" :options="$glAccounts" :value="$r?->gl_account_id" placeholder="—" />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="2" />
    </div>
</x-admin.step>

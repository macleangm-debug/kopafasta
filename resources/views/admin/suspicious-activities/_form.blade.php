@php($r = $record ?? null)
<x-admin.step title="Suspicious activity">
    <x-admin.select name="customer_id" label="Customer" :options="$customers" :value="$r?->customer_id" placeholder="—" />
    <x-admin.select name="loan_id"     label="Loan"     :options="$loans"     :value="$r?->loan_id" placeholder="—" />
    <x-admin.select name="aml_rule_id" label="Triggered AML rule" :options="$amlRules" :value="$r?->aml_rule_id" placeholder="—" />
    <x-admin.input  name="activity_type" label="Activity type" :value="$r?->activity_type" required placeholder="large_txn / velocity / pattern…" />
    <x-admin.input  name="amount"   label="Amount"   money :decimals="2" :value="$r?->amount" />
    <x-admin.select name="severity" label="Severity" :options="$severities" :value="$r?->severity ?? 'medium'" required />
    <x-admin.select name="status"   label="Status"   :options="$statuses"   :value="$r?->status ?? 'open'" required />
    <x-admin.select name="assigned_to_user_id" label="Assigned to" :options="$users" :value="$r?->assigned_to_user_id" placeholder="—" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" required />
    </div>
    <div class="md:col-span-2">
        <x-admin.textarea name="investigator_notes" label="Investigator notes" :value="$r?->investigator_notes" rows="3" />
    </div>
</x-admin.step>

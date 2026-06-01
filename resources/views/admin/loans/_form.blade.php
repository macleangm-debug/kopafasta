{{--
    Shared loan form fields. Expects:
    - $loan (Loan|null)
    - $customers, $products, $applications, $statuses
--}}
@php($l = $loan ?? null)

<x-admin.step title="Borrower & product">
    <x-admin.select name="customer_id"
                    label="Customer"
                    :options="collect($customers)->mapWithKeys(fn ($c) => [$c->id => trim($c->first_name.' '.$c->last_name).($c->customer_number ? ' ('.$c->customer_number.')' : '')])->all()"
                    :value="$l?->customer_id"
                    required
                    placeholder="— Select customer —" />
    <x-admin.select name="loan_product_id"
                    label="Loan product"
                    :options="collect($products)->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->code ? ' ('.$p->code.')' : '')])->all()"
                    :value="$l?->loan_product_id"
                    required
                    placeholder="— Select product —" />
    <x-admin.select name="loan_application_id"
                    label="Linked application"
                    :options="collect($applications)->mapWithKeys(fn ($a) => [$a->id => $a->application_number ?? '#'.$a->id])->all()"
                    :value="$l?->loan_application_id"
                    placeholder="— None —" />
    <x-admin.input  name="loan_number" label="Loan number" :value="$l?->loan_number" placeholder="Auto-generated if blank" />
</x-admin.step>

<x-admin.step title="Amount & terms">
    <x-admin.input name="principal_amount"    label="Principal amount (TZS)"             :value="$l?->principal_amount"    type="number" step="0.01" required />
    <x-admin.input name="approved_amount"     label="Approved amount (TZS)"              :value="$l?->approved_amount"     type="number" step="0.01" placeholder="Defaults to principal" />
    <x-admin.input name="outstanding_balance" label="Outstanding balance (TZS)"          :value="$l?->outstanding_balance" type="number" step="0.01" placeholder="Defaults to principal" />
    <x-admin.input name="interest_rate"       label="Interest rate (decimal, e.g. 0.15)" :value="$l?->interest_rate"       type="number" step="0.0001" required />
    <x-admin.input name="tenure_months"       label="Tenure (months)"                    :value="$l?->tenure_months"       type="number" required />
</x-admin.step>

<x-admin.step title="Schedule & status">
    <x-admin.select name="status"
                    label="Status"
                    :options="display_options($statuses, 'loan_status')"
                    :value="$l?->status ?? 'pending'"
                    required />
    <x-admin.input name="disbursement_date" label="Disbursement date" :value="optional($l?->disbursement_date)->format('Y-m-d')" type="date" />
    <x-admin.input name="maturity_date"     label="Maturity date"     :value="optional($l?->maturity_date)->format('Y-m-d')"     type="date" />
    <x-admin.input name="next_due_date"     label="Next due date"     :value="optional($l?->next_due_date)->format('Y-m-d')"     type="date" />
</x-admin.step>

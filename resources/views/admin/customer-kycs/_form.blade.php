{{-- Customer KYC form. Expects $record, $customers, $reviewers, $statuses --}}
@php($r = $record ?? null)
@php($payloadJson = $r && $r->payload ? json_encode($r->payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null)

<x-admin.step title="Customer">
    <x-admin.select name="customer_id" label="Customer"    :options="$customers" :value="$r?->customer_id" required placeholder="— Select customer —" />
    <x-admin.select name="status"      label="Status"      :options="$statuses"  :value="$r?->status ?? 'pending'" required />
</x-admin.step>

<x-admin.step title="Review">
    <x-admin.select name="verified_by" label="Verified by" :options="$reviewers" :value="$r?->verified_by" placeholder="— None —" />
    <x-admin.input  name="verified_at" label="Verified at" :value="optional($r?->verified_at)->format('Y-m-d')" type="date" />
    <div class="md:col-span-2">
        <x-admin.textarea name="payload" label="Payload (JSON)" :value="$payloadJson" rows="6" help="Optional JSON body containing KYC data." />
    </div>
</x-admin.step>

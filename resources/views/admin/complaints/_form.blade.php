{{-- Complaint form. Expects $record, $customers, $agents, $severities, $statuses, $channels --}}
@php($r = $record ?? null)

<x-admin.step title="Subject">
    <x-admin.input  name="complaint_number" label="Complaint #" :value="$r?->complaint_number" placeholder="Auto-generated if blank" />
    <x-admin.input  name="subject"          label="Subject"     :value="$r?->subject" required />
    <x-admin.select name="customer_id"      label="Customer"    :options="$customers"  :value="$r?->customer_id" placeholder="— None —" />
    <x-admin.select name="channel"          label="Channel"     :options="$channels"   :value="$r?->channel"   placeholder="— Select —" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description"      label="Description"      :value="$r?->description"      rows="3" />
    </div>
</x-admin.step>

<x-admin.step title="Resolution">
    <x-admin.select name="handled_by"       label="Handled by"  :options="$agents"     :value="$r?->handled_by"  placeholder="— Unassigned —" />
    <x-admin.select name="severity"         label="Severity"    :options="$severities" :value="$r?->severity ?? 'medium'" required />
    <x-admin.select name="status"           label="Status"      :options="$statuses"   :value="$r?->status ?? 'open'"     required />
    <x-admin.input  name="resolved_at"      label="Resolved at" :value="optional($r?->resolved_at)->format('Y-m-d')" type="date" />
    <div class="md:col-span-2">
        <x-admin.textarea name="resolution_notes" label="Resolution notes" :value="$r?->resolution_notes" rows="2" />
    </div>
</x-admin.step>

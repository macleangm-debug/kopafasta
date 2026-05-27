{{-- Support ticket form. Expects $record, $customers, $agents, $priorities, $statuses --}}
@php($r = $record ?? null)

<x-admin.step title="Subject">
    <x-admin.input  name="ticket_number" label="Ticket #"   :value="$r?->ticket_number" placeholder="Auto-generated if blank" />
    <x-admin.input  name="subject"       label="Subject"    :value="$r?->subject" required />
    <x-admin.select name="customer_id"   label="Customer"   :options="$customers"  :value="$r?->customer_id" placeholder="— None —" />
    <x-admin.input  name="category"      label="Category"   :value="$r?->category" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description"      label="Description"      :value="$r?->description"      rows="3" />
    </div>
</x-admin.step>

<x-admin.step title="Triage">
    <x-admin.select name="assigned_to"   label="Assigned to" :options="$agents"    :value="$r?->assigned_to" placeholder="— Unassigned —" />
    <x-admin.select name="priority"      label="Priority"   :options="$priorities" :value="$r?->priority ?? 'normal'" required />
    <x-admin.select name="status"        label="Status"     :options="$statuses"   :value="$r?->status ?? 'open'" required />
    <x-admin.input  name="resolved_at"   label="Resolved at" :value="optional($r?->resolved_at)->format('Y-m-d')" type="date" />
    <div class="md:col-span-2">
        <x-admin.textarea name="resolution_notes" label="Resolution notes" :value="$r?->resolution_notes" rows="2" />
    </div>
</x-admin.step>

{{-- Support ticket form. Expects $record, $customers, $agents, $priorities, $statuses, $categories, $sources, $contactKinds --}}
@php($r = $record ?? null)

<x-admin.step title="Contact">
    <x-admin.select name="contact_kind" label="Contact kind" :options="$contactKinds" :value="$r?->contact_kind ?? 'customer'" />
    <x-admin.select name="customer_id"  label="Customer"    :options="$customers"  :value="$r?->customer_id" placeholder="— None / guest —" />
    <x-admin.input  name="guest_name"   label="Guest name"  :value="$r?->guest_name" />
    <x-admin.input  name="guest_email"  label="Guest email" :value="$r?->guest_email" type="email" />
    <x-admin.input  name="guest_phone"  label="Guest phone" :value="$r?->guest_phone" />
    <x-admin.select name="source"       label="Source"      :options="$sources" :value="$r?->source ?? 'admin'" />
</x-admin.step>

<x-admin.step title="Subject">
    <x-admin.input  name="ticket_number" label="Ticket #"   :value="$r?->ticket_number" placeholder="Auto-generated if blank" />
    <x-admin.select name="category"      label="Category"   :options="$categories" :value="$r?->category" placeholder="— Select —" />
    <x-admin.input  name="subject"       label="Subject"    :value="$r?->subject" required />
    <x-admin.input  name="subject_other" label="Subject (if Other)" :value="old('subject_other')" placeholder="Custom subject when using Other" />
    <div class="md:col-span-2">
        <x-admin.textarea name="description" label="Description" :value="$r?->description" rows="3" />
    </div>
</x-admin.step>

<x-admin.step title="Triage">
    <x-admin.select name="assigned_to"   label="Assigned to" :options="$agents"    :value="$r?->assigned_to" placeholder="— Auto-assign —" />
    <x-admin.select name="priority"      label="Priority"   :options="$priorities" :value="$r?->priority ?? 'normal'" required />
    <x-admin.select name="status"        label="Status"     :options="$statuses"   :value="$r?->status ?? 'open'" required />
    <x-admin.input  name="resolved_at"   label="Resolved at" :value="optional($r?->resolved_at)->format('Y-m-d')" type="date" />
    <div class="md:col-span-2">
        <x-admin.textarea name="resolution_notes" label="Resolution notes" :value="$r?->resolution_notes" rows="2" />
    </div>
</x-admin.step>

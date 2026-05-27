@php($r = $record ?? null)
<x-admin.step title="Notification template">
    <x-admin.input  name="code"    label="Code"    :value="$r?->code" required placeholder="loan_approved, payment_reminder…" />
    <x-admin.input  name="name"    label="Name"    :value="$r?->name" required />
    <x-admin.select name="channel" label="Channel" :options="$channels" :value="$r?->channel ?? 'sms'" required />
    <x-admin.select name="is_active" label="Status" :options="['1'=>'Active','0'=>'Inactive']" :value="(string)($r?->is_active ?? '1')" required />
    <div class="md:col-span-2">
        <x-admin.input name="subject" label="Subject (email/push title)" :value="$r?->subject" />
    </div>
    <div class="md:col-span-2">
        <x-admin.textarea name="body" label="Body (supports variables like customer_name, amount, etc.)" :value="$r?->body" rows="8" required />
    </div>
</x-admin.step>

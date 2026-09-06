@php
    $values = $values ?? \App\Models\Setting::group('gateway');
@endphp

<form method="POST" action="{{ route('admin.settings.gateways.save') }}" class="space-y-4" data-no-draft>
    @csrf @method('PUT')
    <div>
        <h3 class="text-sm font-semibold text-gray-900">Email (SMTP) configuration</h3>
        <p class="mt-1 text-xs text-gray-500">One outbound email engine for borrowers, affiliates, capital, recovery and other approved recipients. Templates and triggers live under Communications.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-admin.input name="email_provider" label="Provider (smtp/ses/mailgun)" :value="$values['email_provider'] ?? 'smtp'" />
        <x-admin.input name="email_from_address" label="From address" type="email" :value="$values['email_from_address'] ?? ''" />
        <x-admin.input name="email_from_name" label="From name" :value="$values['email_from_name'] ?? ''" />
        <x-admin.input name="email_encryption" label="Encryption (tls/ssl)" :value="$values['email_encryption'] ?? 'tls'" />
        <x-admin.input name="email_smtp_host" label="SMTP host" :value="$values['email_smtp_host'] ?? ''" />
        <x-admin.input name="email_smtp_port" label="SMTP port" type="number" :value="$values['email_smtp_port'] ?? '587'" />
        <x-admin.input name="email_smtp_user" label="SMTP username" :value="$values['email_smtp_user'] ?? ''" />
        <x-admin.input name="email_smtp_pass" label="SMTP password" type="password" :value="$values['email_smtp_pass'] ?? ''" data-no-draft />
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <button type="submit" name="intent" value="save" class="rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5">Save settings</button>
        <a href="{{ route('admin.settings.messaging') }}" class="text-xs font-semibold text-brand hover:underline">Transactional messaging templates →</a>
    </div>
</form>

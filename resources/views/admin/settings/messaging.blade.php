<x-admin.layout title="Transactional messaging" heading="Transactional messaging" subheading="Turn automated SMS, email, in-app and WhatsApp on or off per event">
    @include('admin.settings._tabs', ['active' => 'messaging'])

<div class="mb-6 rounded-xl bg-brand text-white px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
        <div>
            <p class="text-sm font-semibold">Edit message content</p>
            <p class="text-xs text-white/80 mt-1">Subject and body for each transactional SMS/email live in Notification templates (English + Kiswahili). Use personalization chips like <code class="text-brand-gold">@{{ name }}</code>.</p>
        </div>
        <a href="{{ route('admin.notification-templates.index') }}"
           class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5 hover:brightness-95 shrink-0">
            Open notification templates →
        </a>
    </div>

    <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900 space-y-2">
        <p class="font-semibold">What you need for live delivery</p>
        <ul class="list-disc pl-5 space-y-1 text-sky-800">
            <li><strong>SMS:</strong> Unitxt credentials under Settings → SMS / Email (provider <code class="text-xs">unitxt</code>, Sender ID + API key).</li>
            <li><strong>Email:</strong> Configure Laravel mail in <code class="text-xs">.env</code> (or SMTP fields on the gateways page).</li>
            <li><strong>OTP & e-receipts:</strong> PIN/agreement OTP, loan disbursed, and payment received are marked critical — they cannot be turned off while messaging is enabled.</li>
            <li><strong>WhatsApp:</strong> Optional. Leave off until you have a Business API URL + token; messages log only while provider is <code class="text-xs">log</code>.</li>
            <li><strong>Message copy:</strong> Use the button above — do not edit wording on this page.</li>
            <li><strong>Scheduler:</strong> Server cron must run <code class="text-xs">php artisan schedule:run</code> every minute for repayment/membership reminders.</li>
        </ul>
    </div>

    <form method="POST" action="{{ route('admin.settings.messaging.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-gray-800">Global controls</h3>
            <label class="flex items-start gap-3 text-sm">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked($enabled) class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span>
                    <span class="font-medium text-gray-900">Enable transactional messaging</span>
                    <span class="block text-xs text-gray-500 mt-0.5">Master kill switch. When off, SMS/email/WhatsApp/in-app product messages are skipped (logged as skipped).</span>
                </span>
            </label>
            <label class="flex items-start gap-3 text-sm">
                <input type="hidden" name="force_log_driver" value="0">
                <input type="checkbox" name="force_log_driver" value="1" @checked($force_log_driver) class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                <span>
                    <span class="font-medium text-gray-900">Force log mode (no live SMS/WhatsApp API calls)</span>
                    <span class="block text-xs text-gray-500 mt-0.5">Use on staging. Messages are recorded but providers are not called.</span>
                </span>
            </label>

            <div class="grid sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Repayment reminder offsets (days before due)</label>
                    <input type="text" name="reminder_offsets_days" value="{{ old('reminder_offsets_days', $reminder_offsets_days) }}"
                           class="w-full rounded-lg border-gray-300 text-sm" placeholder="3,1,0">
                    <p class="mt-1 text-[11px] text-gray-500">Example: <code>3,1,0</code> = 3 days before, 1 day before, and due today.</p>
                </div>
                <label class="flex items-start gap-3 text-sm sm:pt-6">
                    <input type="hidden" name="overdue_reminders" value="0">
                    <input type="checkbox" name="overdue_reminders" value="1" @checked($overdue_reminders) class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                    <span>
                        <span class="font-medium text-gray-900">Send 1-day overdue reminders</span>
                        <span class="block text-xs text-gray-500 mt-0.5">Also requires the “Repayment overdue” event below.</span>
                    </span>
                </label>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">Channels</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach ($channel_labels as $key => $label)
                    <label class="flex items-center gap-2 text-sm rounded-lg bg-gray-50 ring-1 ring-gray-200 px-3 py-2.5">
                        <input type="hidden" name="channels[{{ $key }}]" value="0">
                        <input type="checkbox" name="channels[{{ $key }}]" value="1" @checked(! empty($channels[$key])) class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                        <span class="text-gray-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-1">WhatsApp API (optional)</h3>
            <p class="text-xs text-gray-500 mb-4">Stub is ready. Set provider to <code>http</code> / <code>meta</code> and paste Cloud API credentials when available. Until then keep WhatsApp channel off or provider = log.</p>
            <div class="grid sm:grid-cols-2 gap-4">
                <x-admin.input name="whatsapp[provider]" label="Provider (log / http / meta)" :value="$whatsapp['provider'] ?? 'log'" />
                <x-admin.input name="whatsapp[from_number]" label="From number / WABA phone id" :value="$whatsapp['from_number'] ?? ''" />
                <div class="sm:col-span-2"><x-admin.input name="whatsapp[api_url]" label="API URL" :value="$whatsapp['api_url'] ?? ''" /></div>
                <div class="sm:col-span-2"><x-admin.input name="whatsapp[api_token]" label="API token" :value="$whatsapp['api_token'] ?? ''" /></div>
            </div>
        </div>

        @foreach ($groups as $groupKey => $groupLabel)
            @php
                $groupEvents = collect($catalog)->where('group', $groupKey)->values();
            @endphp
            @continue($groupEvents->isEmpty())
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-800">{{ $groupLabel }}</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($groupEvents as $event)
                        @php
                            $code = $event['code'];
                            $row = $events[$code] ?? ['enabled' => $event['default_enabled'], 'channels' => $event['default_channels']];
                            $selectedChannels = (array) ($row['channels'] ?? $event['default_channels']);
                        @endphp
                        <div class="px-6 py-4 grid lg:grid-cols-12 gap-3 items-start">
                            <div class="lg:col-span-5">
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="hidden" name="events[{{ $code }}][enabled]" value="{{ $event['critical'] ? '1' : '0' }}">
                                    <input type="checkbox" name="events[{{ $code }}][enabled]" value="1"
                                           @checked($event['critical'] || ! empty($row['enabled']))
                                           @disabled($event['critical'])
                                           class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                                    <span>
                                        <span class="font-medium text-gray-900">{{ $event['name'] }}</span>
                                        @if ($event['critical'])
                                            <span class="ml-1 inline-flex text-[10px] uppercase tracking-wide font-bold text-rose-700 bg-rose-50 ring-1 ring-rose-200 rounded px-1.5 py-0.5">Critical</span>
                                        @endif
                                        <span class="block text-xs text-gray-500 mt-0.5">{{ $event['description'] }}</span>
                                        <span class="block text-[11px] font-mono text-gray-400 mt-1">{{ $code }}</span>
                                    </span>
                                </label>
                            </div>
                            <div class="lg:col-span-7 flex flex-wrap gap-2">
                                @foreach ($channel_labels as $ch => $chLabel)
                                    <label class="inline-flex items-center gap-1.5 text-xs rounded-lg ring-1 ring-gray-200 px-2.5 py-1.5 bg-white">
                                        <input type="checkbox" name="events[{{ $code }}][channels][]" value="{{ $ch }}"
                                               @checked(in_array($ch, $selectedChannels, true))
                                               class="size-3.5 rounded border-gray-300 text-brand focus:ring-brand">
                                        {{ $chLabel }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-lg shadow-sm">
                Save messaging settings
            </button>
        </div>
    </form>
</x-admin.layout>

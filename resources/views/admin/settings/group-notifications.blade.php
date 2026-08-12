<x-admin.layout title="Group notifications" heading="Group notifications" subheading="Control which group-loan notices leaders and members receive">
    @include('admin.settings._tabs', ['active' => 'group-notifications'])

    <div class="mb-6 rounded-xl bg-brand text-white px-5 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-sm">
        <div>
            <p class="text-sm font-semibold">Edit message content</p>
            <p class="text-xs text-white/80 mt-1">Subject and body for each notice live in Notification templates (English + Kiswahili). This page only turns events and channels on or off.</p>
        </div>
        <a href="{{ route('admin.notification-templates.index', ['stage' => 'group']) }}"
           class="inline-flex items-center justify-center rounded-xl bg-brand-gold text-brand text-sm font-semibold px-4 py-2.5 hover:brightness-95 shrink-0">
            Open group templates →
        </a>
    </div>

    <div class="mb-6 rounded-xl bg-sky-50 ring-1 ring-sky-200 px-4 py-3 text-sm text-sky-900 space-y-2">
        <p class="font-semibold">How these settings work</p>
        <ul class="list-disc pl-5 space-y-1 text-sky-800">
            <li>Toggles here write into the same <code class="text-xs">messaging.events</code> store as Transactional messaging — only group lending events.</li>
            <li>Global channel kill-switches (SMS, email, WhatsApp, …) still apply from
                <a href="{{ route('admin.settings.messaging') }}" class="font-semibold underline underline-offset-2">Transactional messaging</a>.</li>
            @if (! $globally_enabled)
                <li class="font-semibold text-rose-800">Transactional messaging is currently off — group notices will not send until the master switch is enabled.</li>
            @endif
            <li>Borrower inbox filters: enable the <strong>Group loan</strong> category under Engagement → Notifications so members can filter these in-app.</li>
        </ul>
    </div>

    @php
        $memberEvents = collect($catalog)->where('audience', 'member')->values();
        $leaderEvents = collect($catalog)->where('audience', 'leader')->values();
        $otherEvents = collect($catalog)->reject(fn ($e) => in_array($e['audience'] ?? null, ['member', 'leader'], true))->values();
        $sections = [
            ['title' => 'Member notices', 'hint' => 'Sent to invited / participating group members.', 'events' => $memberEvents],
            ['title' => 'Leader notices', 'hint' => 'Sent to the group loan applicant (leader).', 'events' => $leaderEvents],
        ];
        if ($otherEvents->isNotEmpty()) {
            $sections[] = ['title' => 'Other group notices', 'hint' => '', 'events' => $otherEvents];
        }
    @endphp

    <form method="POST" action="{{ route('admin.settings.group-notifications.save') }}" class="space-y-6">
        @csrf @method('PUT')

        @foreach ($sections as $section)
            @continue($section['events']->isEmpty())
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                <div class="px-6 py-3 border-b border-gray-100 bg-gray-50">
                    <h3 class="text-sm font-semibold text-gray-800">{{ $section['title'] }}</h3>
                    @if ($section['hint'] !== '')
                        <p class="text-xs text-gray-500 mt-0.5">{{ $section['hint'] }}</p>
                    @endif
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($section['events'] as $event)
                        @php
                            $code = $event['code'];
                            $row = $events[$code] ?? ['enabled' => $event['default_enabled'], 'channels' => $event['default_channels']];
                            $selectedChannels = (array) ($row['channels'] ?? $event['default_channels']);
                        @endphp
                        <div class="px-6 py-4 grid lg:grid-cols-12 gap-3 items-start">
                            <div class="lg:col-span-5">
                                <label class="flex items-start gap-2 text-sm">
                                    <input type="hidden" name="events[{{ $code }}][enabled]" value="0">
                                    <input type="checkbox" name="events[{{ $code }}][enabled]" value="1"
                                           @checked(! empty($row['enabled']))
                                           class="mt-0.5 size-4 rounded border-gray-300 text-brand focus:ring-brand">
                                    <span>
                                        <span class="font-medium text-gray-900">{{ $event['name'] }}</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">{{ $event['description'] }}</span>
                                        <span class="block text-[11px] font-mono text-gray-400 mt-1">{{ $code }}</span>
                                    </span>
                                </label>
                            </div>
                            <div class="lg:col-span-7 flex flex-wrap gap-2">
                                @foreach ($channel_labels as $ch => $chLabel)
                                    @php
                                        $channelOn = ! empty($channel_flags[$ch]);
                                    @endphp
                                    <label class="inline-flex items-center gap-1.5 text-xs rounded-lg ring-1 px-2.5 py-1.5 {{ $channelOn ? 'ring-gray-200 bg-white' : 'ring-amber-200 bg-amber-50 text-amber-900' }}"
                                           @unless($channelOn) title="Global channel is off under Transactional messaging" @endunless>
                                        <input type="checkbox" name="events[{{ $code }}][channels][]" value="{{ $ch }}"
                                               @checked(in_array($ch, $selectedChannels, true))
                                               class="size-3.5 rounded border-gray-300 text-brand focus:ring-brand">
                                        {{ $chLabel }}
                                        @unless ($channelOn)
                                            <span class="text-[10px] font-semibold uppercase tracking-wide">off</span>
                                        @endunless
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <a href="{{ route('admin.settings.engagement.notifications') }}" class="text-sm font-semibold text-brand hover:underline">
                Inbox category filters (Engagement) →
            </a>
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-lg shadow-sm">
                Save group notifications
            </button>
        </div>
    </form>
</x-admin.layout>

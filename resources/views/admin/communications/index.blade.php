<x-admin.layout title="Communications" heading="Communications" subheading="Edit message content here. Gateways and quiet hours stay in Settings.">
    <div class="flex flex-wrap gap-2 mb-6">
        @can('communications.templates.manage')
            <a href="{{ route('admin.notification-templates.create') }}" class="inline-flex items-center rounded-xl bg-brand-gold text-brand text-sm font-bold px-4 py-2.5">+ Template</a>
        @endcan
        @can('communications.chatbot.manage')
            <a href="{{ route('admin.communications.chatbot') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">Edit chatbot</a>
        @endcan
        @can('support.tickets')
            <a href="{{ route('admin.support-tickets.index') }}" class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/15 text-sm font-semibold px-4 py-2.5">Open tickets</a>
        @endcan
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Sent today</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['sent']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Delivered</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['delivered']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Failed</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['failed']) }}</p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-brand/10 p-4">
            <p class="text-[10px] uppercase tracking-widest text-gray-500 font-semibold">Scheduled</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-brand">{{ \App\Support\MoneyFormat::compact($stats['scheduled']) }}</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-5 mb-6">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5">
            <h2 class="text-sm font-bold text-gray-900">Needs attention</h2>
            <div class="hidden md:block mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 pr-3">Issue</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failedRows as $row)
                            <tr class="border-t border-gray-100">
                                <td class="py-2 pr-3">Failed delivery · {{ $row->template ?: $row->channel }}</td>
                                <td class="py-2">{{ $row->status }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="py-3 text-gray-500">No failed deliveries in the recent queue.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <ul class="md:hidden mt-3 space-y-2 text-sm">
                @forelse ($failedRows as $row)
                    <li class="rounded-xl bg-red-50 ring-1 ring-red-100 px-3 py-2">Failed delivery · {{ $row->template ?: $row->channel }} · {{ $row->status }}</li>
                @empty
                    <li class="text-gray-500">No failed deliveries in the recent queue.</li>
                @endforelse
            </ul>
            <ul class="mt-3 space-y-2 text-sm">
                @if ($missingTranslation > 0)
                    <li class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                        {{ $missingTranslation }} template{{ $missingTranslation === 1 ? '' : 's' }} needing EN/SW copy
                        @if ($translationCodes)
                            ({{ implode(', ', $translationCodes) }})
                        @endif
                        — <a href="{{ route('admin.notification-templates.index') }}" class="font-semibold text-brand">Open templates</a>
                    </li>
                @endif
                @if ($scheduledCampaigns > 0)
                    <li class="rounded-xl bg-amber-50 ring-1 ring-amber-100 px-3 py-2">
                        {{ $scheduledCampaigns }} scheduled campaign{{ $scheduledCampaigns === 1 ? '' : 's' }}
                        — <a href="{{ route('admin.promotions.index') }}" class="font-semibold text-brand">Review</a>
                    </li>
                @endif
            </ul>
        </section>
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 p-5 space-y-3">
            <h2 class="text-sm font-bold text-gray-900">Operate</h2>
            <a href="{{ route('admin.notification-templates.index') }}" class="block rounded-xl ring-1 ring-gray-100 px-3 py-2 hover:bg-brand-muted/30">
                <p class="font-semibold">Templates</p>
                <p class="text-xs text-gray-500">{{ $templateCount }} events. Edit wording without settings.manage.</p>
            </a>
            <a href="{{ route('admin.communications.chatbot') }}" class="block rounded-xl ring-1 ring-gray-100 px-3 py-2 hover:bg-brand-muted/30">
                <p class="font-semibold">Chatbot</p>
                <p class="text-xs text-gray-500">FAQ content. Behaviour stays in Settings if configured there.</p>
            </a>
            <a href="{{ route('admin.support-tickets.index') }}" class="block rounded-xl ring-1 ring-gray-100 px-3 py-2 hover:bg-brand-muted/30">
                <p class="font-semibold">Tickets</p>
                <p class="text-xs text-gray-500">{{ number_format($ticketCount) }} cases.</p>
            </a>
        </section>
    </div>

    @can('settings.manage')
        <p class="text-xs text-gray-500">Provider configuration remains Settings-only. <a href="{{ route('admin.settings.messaging') }}" class="font-semibold text-brand">Open Messaging settings →</a></p>
    @endcan
</x-admin.layout>

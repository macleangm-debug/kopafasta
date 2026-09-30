<x-admin.layout title="Notifications" heading="" subheading="">
    <x-admin.letterhead
        kicker="Customer Support"
        title="Notifications"
        subtitle="What needs attention in your support queue. Dedicated support push notifications are not wired yet — this list reuses waiting conversations and open cases." />

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Waiting conversations</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse (($dashboard['queue'] ?? []) as $item)
                    @continue(! ($item['needs_human'] ?? false) && ($item['status'] ?? '') !== 'open')
                    <li>
                        <a href="{{ $item['url'] }}" class="block px-5 py-3.5 hover:bg-brand-muted/20">
                            <p class="text-sm font-semibold text-gray-900">{{ $item['name'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $item['preview'] }}</p>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-gray-500 text-center">No waiting items.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-900">Open cases</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse (($dashboard['tickets'] ?? []) as $ticket)
                    <li>
                        <a href="{{ $ticket['url'] }}" class="block px-5 py-3.5 hover:bg-brand-muted/20">
                            <p class="text-sm font-semibold text-gray-900">{{ $ticket['contact'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $ticket['number'] }} · {{ $ticket['subject'] }}</p>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-sm text-gray-500 text-center">No open cases.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-admin.layout>

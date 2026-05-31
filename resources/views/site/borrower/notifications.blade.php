<x-site.borrower-layout title="Notifications — Kopafasta" active="notifications">

    <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold mb-1">Notifications</h1>
            <p class="text-sm text-gray-500">Updates about your loan, payments, identity and documents.</p>
        </div>
        @if ($items->total() > 0)
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('site.borrower.notifications.read') }}">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-full ring-1 ring-gray-200 bg-white hover:bg-gray-50">Mark all read</button>
                </form>
                <form method="POST" action="{{ route('site.borrower.notifications.clear-all') }}" onsubmit="return confirm('Clear all notifications?')">
                    @csrf
                    <button class="text-xs font-semibold px-4 py-2 rounded-full ring-1 ring-red-200 text-red-700 bg-red-50 hover:bg-red-100">Clear all</button>
                </form>
            </div>
        @endif
    </div>

    @if ($items->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-gray-100 grid place-items-center text-gray-400">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
            </div>
            <p class="text-gray-500">No notifications yet.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($items as $n)
                <div class="bg-white rounded-2xl border border-gray-200 p-5 flex gap-4 {{ $n->read_at ? '' : 'ring-2 ring-amber-100' }}">
                    <div class="size-10 rounded-full shrink-0 grid place-items-center {{ $n->read_at ? 'bg-gray-100 text-gray-500' : 'bg-amber-100 text-amber-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            @if ($n->category)
                                <span class="text-[10px] uppercase tracking-widest font-semibold text-gray-500">{{ $n->category }}</span>
                            @endif
                            @unless ($n->read_at)
                                <span class="text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800">Unread</span>
                            @endunless
                        </div>
                        <p class="text-sm text-gray-800">{{ $n->message ?: $n->template }}</p>
                        <p class="text-xs text-gray-400 mt-2">{{ \Carbon\Carbon::parse($n->created_at)->format('d M Y, H:i') }} · {{ strtoupper($n->channel) }}</p>
                    </div>
                    <div class="flex flex-col gap-2 shrink-0">
                        @unless ($n->read_at)
                            <form method="POST" action="{{ route('site.borrower.notifications.item.read', $n) }}">
                                @csrf
                                <button class="text-xs font-semibold text-amber-700 hover:underline">Mark read</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('site.borrower.notifications.item.clear', $n) }}">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold text-gray-500 hover:text-red-600">Clear</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-6">{{ $items->links() }}</div>
    @endif

</x-site.borrower-layout>

<x-site.borrower-layout title="Notifications — Kopafasta" active="notifications">

    <h1 class="text-2xl font-bold mb-1">Notifications</h1>
    <p class="text-sm text-gray-500 mb-6">Updates about your loan, payments and documents.</p>

    @if ($items->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-12 text-center">
            <p class="text-gray-500">No notifications yet.</p>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200">
            <ul class="divide-y divide-gray-100">
                @foreach ($items as $n)
                    <li class="px-5 py-4 flex gap-4">
                        <div class="size-9 rounded-full bg-amber-100 text-amber-700 grid place-items-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9z"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-gray-800">{{ $n->message ?: $n->template }}</p>
                            <p class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($n->created_at)->diffForHumans() }} · {{ strtoupper($n->channel) }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    @endif

</x-site.borrower-layout>

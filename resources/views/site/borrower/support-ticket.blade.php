<x-site.borrower-layout :title="brand_title($ticket->ticket_number ?: 'Support ticket')" active="support" content-width="wide">
    @php
        $isSw = str_starts_with(app()->getLocale(), 'sw');
        $categories = \App\Support\SupportTaxonomy::categories();
        $categoryKey = (string) ($ticket->category ?? '');
        $categoryLabel = $categories[$categoryKey] ?? ($categoryKey !== '' ? ucwords(str_replace('_', ' ', $categoryKey)) : ($isSw ? 'Suala' : 'Issue'));
        $status = (string) $ticket->status;
        $statusLabel = match ($status) {
            'open' => $isSw ? 'Imefunguliwa' : 'Open',
            'in_progress', 'waiting' => $isSw ? 'Inaendelea' : 'In progress',
            'resolved' => $isSw ? 'Imetatuliwa' : 'Resolved',
            'closed' => $isSw ? 'Imefungwa' : 'Closed',
            default => ucfirst($status),
        };
        $isOpen = in_array($status, ['open', 'in_progress', 'waiting'], true);
        $conversation = $ticket->conversation;
        $conversationOpen = $conversation
            && ! in_array((string) $conversation->status, ['closed', 'resolved'], true);
        $displayNumber = $ticket->publicNumber();
        $hasRating = (bool) ($ticket->rating?->rating ?? null);
    @endphp

    <div class="mb-4">
        <a href="{{ route('site.borrower.support', ['section' => $isOpen ? 'active' : 'history']) }}"
           class="text-sm font-semibold text-brand hover:underline">← {{ $isSw ? 'Kituo cha Usaidizi' : 'Help Center' }}</a>
    </div>

    <div class="max-w-2xl overflow-hidden rounded-2xl ring-1 ring-brand/15 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-brand via-[#127A5F] to-[#0a4a3c] text-white px-4 py-4">
            <p class="text-[11px] uppercase tracking-widest font-semibold text-white/70">{{ $displayNumber }}</p>
            <p class="text-base font-bold mt-1">{{ $ticket->subject }}</p>
            <p class="text-xs text-white/75 mt-1">
                {{ $categoryLabel }}
                · {{ $statusLabel }}
            </p>
        </div>

        <dl class="px-4 py-4 space-y-3 text-sm">
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">{{ $isSw ? 'Hali' : 'Status' }}</dt>
                <dd class="font-semibold text-gray-900">{{ $statusLabel }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">{{ $isSw ? 'Imeundwa' : 'Created' }}</dt>
                <dd class="font-medium text-gray-900">{{ format_app_datetime($ticket->created_at, 'd M Y · H:i') }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt class="text-gray-500">{{ $isSw ? 'Imesasishwa' : 'Updated' }}</dt>
                <dd class="font-medium text-gray-900">{{ format_app_datetime($ticket->updated_at, 'd M Y · H:i') }}</dd>
            </div>
            @if ($conversation)
                <div class="flex justify-between gap-3">
                    <dt class="text-gray-500">{{ $isSw ? 'Mazungumzo' : 'Conversation' }}</dt>
                    <dd class="font-semibold text-brand tabular-nums">{{ $conversation->publicNumber() }}</dd>
                </div>
            @endif
        </dl>

        @if ($isOpen && $conversationOpen)
            <div class="px-4 pb-4">
                <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                   class="block rounded-2xl bg-brand-gold text-brand text-center text-sm font-bold px-5 py-3.5 hover:brightness-95">
                    {{ $isSw ? 'Endelea mazungumzo' : 'Continue chat' }}
                </a>
            </div>
        @elseif (! $isOpen)
            <div class="px-4 pb-4 border-t border-gray-100 pt-4 space-y-3">
                @if (! $hasRating)
                    <form method="POST" action="{{ route('site.borrower.support.rate', $ticket) }}" class="space-y-3">
                        @csrf
                        <p class="text-sm font-semibold text-gray-900">{{ $isSw ? 'Tathmini huduma (1–5)' : 'Rate support (1–5)' }}</p>
                        <div class="flex gap-2">
                            @for ($i = 1; $i <= 5; $i++)
                                <label class="inline-flex items-center gap-1 text-sm font-semibold">
                                    <input type="radio" name="rating" value="{{ $i }}" required class="text-brand"> {{ $i }}
                                </label>
                            @endfor
                        </div>
                        <textarea name="comment" rows="2" maxlength="500" class="w-full rounded-xl border-gray-200 text-sm"
                                  placeholder="{{ $isSw ? 'Hiari' : 'Optional' }}"></textarea>
                        <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">
                            {{ $isSw ? 'Tuma tathmini' : 'Submit rating' }}
                        </button>
                    </form>
                @else
                    <p class="text-sm text-emerald-800">
                        {{ $isSw ? 'Asante — ulitoa nyota '.$ticket->rating->rating.'.' : 'Thank you — you rated '.$ticket->rating->rating.'.' }}
                    </p>
                @endif
                <p class="text-xs text-gray-500">{{ $isSw ? 'Tiketi hii imefungwa — hakuna ujumbe mpya.' : 'This ticket is closed — messaging is read-only.' }}</p>
            </div>
        @endif
    </div>
</x-site.borrower-layout>

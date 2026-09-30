<x-site.borrower-layout :title="brand_title('Support history')" active="support" content-width="wide">
    <div class="mb-4">
        <a href="{{ route('site.borrower.support') }}" class="text-sm font-semibold text-brand hover:underline">← Support Home</a>
    </div>

    <div class="max-w-2xl overflow-hidden rounded-2xl ring-1 ring-brand/15 bg-white shadow-sm">
        <div class="bg-gradient-to-br from-brand via-[#127A5F] to-[#0a4a3c] text-white px-4 py-3">
            <p class="text-[11px] uppercase tracking-widest font-semibold text-white/70">{{ $conversation->publicNumber() }}</p>
            <p class="text-sm font-bold mt-0.5">{{ $conversation->topic ?: 'Support conversation' }}</p>
            <p class="text-xs text-white/75 mt-0.5">
                {{ ucfirst($conversation->status) }}
                · {{ format_app_datetime($conversation->last_message_at ?? $conversation->created_at, 'd M Y · H:i') }}
            </p>
        </div>
        <div class="p-4 space-y-2.5 bg-[#f7f8fa] max-h-[28rem] overflow-y-auto">
            @foreach ($conversation->messages as $message)
                <x-site.support-chat-bubble
                    :outbound="! in_array($message->sender_type, ['staff', 'bot'], true)"
                    :text="$message->body"
                    :time="format_app_datetime($message->created_at, 'H:i')"
                />
            @endforeach
        </div>

        @if (in_array($conversation->status, ['resolved', 'closed'], true) && ! $conversation->rating)
            <form method="POST" action="{{ route('site.borrower.support.conversation.rate', $conversation) }}" class="p-4 border-t border-gray-100 space-y-3">
                @csrf
                <p class="text-sm font-semibold text-gray-900">Tathmini huduma (1–5)</p>
                <div class="flex gap-2">
                    @for ($i = 1; $i <= 5; $i++)
                        <label class="inline-flex items-center gap-1 text-sm font-semibold">
                            <input type="radio" name="rating" value="{{ $i }}" required class="text-brand"> {{ $i }}
                        </label>
                    @endfor
                </div>
                <textarea name="comment" rows="2" maxlength="500" class="w-full rounded-xl border-gray-200 text-sm" placeholder="Hiari"></textarea>
                <button class="rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">Tuma tathmini</button>
            </form>
        @elseif ($conversation->rating)
            <p class="px-4 py-3 text-sm text-emerald-800 border-t border-gray-100">Asante — ulitoa nyota {{ $conversation->rating }}.</p>
        @endif
    </div>
</x-site.borrower-layout>

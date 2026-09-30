@php
    $isSw = str_starts_with(app()->getLocale(), 'sw');
    $phones = support_phones();
    $primaryPhone = $phones[0] ?? null;
    $hasActive = \App\Models\SupportConversation::query()
        ->where('customer_id', auth()->user()?->customer?->id)
        ->whereNotIn('status', ['closed', 'resolved'])
        ->exists();
@endphp
<div class="fixed bottom-6 right-6 z-40 print:hidden hidden lg:block" x-data="{ open: false }">
    <div x-show="open" @click.outside="open = false" x-cloak
         x-transition class="absolute bottom-16 right-0 w-72 rounded-2xl glass-card overflow-hidden shadow-xl">
        <div class="px-4 py-3 border-b border-gray-100/80 bg-brand text-white">
            <p class="text-sm font-bold">Kopafasta Support</p>
            <p class="text-xs text-white/80 mt-0.5">{{ $isSw ? 'Msaada kutoka akaunti yako' : 'Help from your account' }}</p>
        </div>
        <div class="p-2 bg-white/95">
            <a href="{{ route('site.borrower.support') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">⌕</span>
                {{ $isSw ? 'Tafuta msaada' : 'Search help' }}
            </a>
            <a href="{{ route('site.borrower.support') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">?</span>
                {{ $isSw ? 'FAQ na HOW TO' : 'FAQs & HOW TO' }}
            </a>
            @if ($hasActive)
                <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted">
                    <span class="size-8 rounded-lg bg-brand text-white grid place-items-center shrink-0">💬</span>
                    {{ $isSw ? 'Endelea mazungumzo' : 'Continue support conversation' }}
                </a>
            @endif
            <a href="{{ route('site.borrower.support', ['chat' => 1]) }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">☎</span>
                {{ $isSw ? 'Ongea na Timu ya Usaidizi' : 'Talk to Support' }}
            </a>
            <a href="{{ route('site.feedback', ['open' => 1, 'from' => 'borrower']) }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">✎</span>
                {{ $isSw ? 'Tuma maoni' : 'Send feedback' }}
            </a>
            @if ($primaryPhone)
                <a href="tel:{{ preg_replace('/\s+/', '', $primaryPhone) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">📞</span>
                    {{ $isSw ? 'Piga Kopafasta' : 'Call Kopafasta' }} · {{ $primaryPhone }}
                </a>
            @endif
        </div>
    </div>

    <button type="button" @click="open = !open"
            class="size-14 rounded-full bg-brand text-white shadow-lg hover:bg-brand-light transition grid place-items-center ring-4 ring-white/80"
            :aria-expanded="open" title="Kopafasta Support">
        <span class="text-xl font-bold" x-text="open ? '×' : '?'"></span>
    </button>
</div>

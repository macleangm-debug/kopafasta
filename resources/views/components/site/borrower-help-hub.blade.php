<div class="fixed bottom-6 right-6 z-40 print:hidden" x-data="{ open: false }">
    <div x-show="open" @click.outside="open = false" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute bottom-16 right-0 w-72 rounded-2xl glass-card overflow-hidden shadow-xl">
        <div class="px-4 py-3 border-b border-gray-100/80 bg-brand text-white">
            <p class="text-sm font-bold">{{ __('borrower.layout.help_center') }}</p>
            <p class="text-xs text-white/80 mt-0.5">{{ __('borrower.support_page.subtitle') }}</p>
        </div>
        <div class="p-2 bg-white/95">
            <a href="{{ route('site.borrower.support') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted transition">
                <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/></svg>
                </span>
                {{ __('borrower.layout.help_center') }}
            </a>
            <a href="{{ route('site.faq') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted transition">
                <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">
                    <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h10M4 18h6"/></svg>
                </span>
                {{ __('borrower.layout.help') }}
            </a>
            @foreach (support_phones() as $phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted transition">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </span>
                    {{ $phone }}
                </a>
            @endforeach
            @foreach (support_emails() as $email)
                <a href="mailto:{{ $email }}"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-gray-700 hover:bg-brand-muted transition">
                    <span class="size-8 rounded-lg bg-brand-muted text-brand grid place-items-center shrink-0">
                        <svg class="size-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m4 7 8 6 8-6"/></svg>
                    </span>
                    <span class="truncate">{{ $email }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <button type="button" @click="open = !open"
            class="size-14 rounded-full bg-brand text-white shadow-lg hover:bg-brand-light transition grid place-items-center ring-4 ring-white/80"
            :aria-expanded="open"
            title="{{ __('borrower.layout.help_center') }}">
        <svg x-show="!open" class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 18v.01M9.1 9a3 3 0 1 1 4.4 3.4c-1 .6-1.5 1.2-1.5 2.6M12 22a10 10 0 1 1 0-20 10 10 0 0 1 0 20z"/></svg>
        <svg x-show="open" x-cloak class="size-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
</div>

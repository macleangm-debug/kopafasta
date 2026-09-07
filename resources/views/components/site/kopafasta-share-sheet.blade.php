@props([
    'title',
    'hint' => null,
    'open' => 'shareOpen',
    'showFacebook' => true,
    'whatsappLabel' => 'WhatsApp',
    'facebookLabel' => 'Facebook',
    'messagesLabel' => 'Messages',
    'emailLabel' => 'Email',
    'copyLabel' => 'Copy',
    'copiedLabel' => 'Copied',
    'moreLabel' => 'More',
])

{{-- Canonical Kopafasta share sheet (mobile bottom sheet / desktop modal). Parent Alpine must expose: open flag, shareWhatsApp, shareFacebook, shareMessages, shareEmail, copyShare, shareMore, canNativeShare, copied. --}}
<x-site.action-panel :title="$title" :open="$open" size="md">
    @if (filled($hint))
        <p class="text-sm text-gray-600 mb-4">{{ $hint }}</p>
    @endif
    <div class="grid grid-cols-1 gap-2.5">
        <button type="button" @click="shareWhatsApp()"
                class="flex items-center gap-3 rounded-2xl bg-brand-gold/15 ring-1 ring-brand-gold/30 px-4 py-3.5 hover:bg-brand-gold/25 transition text-left w-full">
            <span class="size-11 rounded-xl bg-[#25D366] text-white grid place-items-center shrink-0" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11 11 0 0 0 2.1 17.7L1 23l5.4-1.4A11 11 0 1 0 20.5 3.5zm-8.6 17a9.1 9.1 0 0 1-4.6-1.3l-.3-.2-3.2.8.9-3.1-.2-.3a9.1 9.1 0 1 1 7.4 4.1zm5-6.8c-.3-.1-1.6-.8-1.8-.9-.2-.1-.4-.1-.6.1-.2.3-.7.9-.8 1-.1.2-.3.2-.6.1-.3-.2-1.2-.4-2.2-1.4-.8-.7-1.4-1.6-1.5-1.9-.2-.3 0-.4.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.6-1.5-.8-2-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.3.3-.9.9-.9 2.1s.9 2.4 1 2.6c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.2.8 3 .7.5-.1 1.6-.6 1.8-1.3.2-.6.2-1.2.1-1.3-.1-.1-.3-.2-.6-.3z"/></svg>
            </span>
            <span class="text-base font-bold text-gray-900">{{ $whatsappLabel }}</span>
        </button>

        @if ($showFacebook)
            <button type="button" @click="shareFacebook()"
                    class="flex items-center gap-3 rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3.5 hover:bg-brand/10 transition text-left w-full">
                <span class="size-11 rounded-xl bg-[#1877F2] text-white grid place-items-center shrink-0" aria-hidden="true">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor"><path d="M14 9h3V6h-3c-2.2 0-4 1.8-4 4v2H7v3h3v7h3v-7h3l1-3h-4v-2c0-.6.4-1 1-1z"/></svg>
                </span>
                <span class="text-base font-bold text-gray-900">{{ $facebookLabel }}</span>
            </button>
        @endif

        <button type="button" @click="shareMessages()"
                class="flex items-center gap-3 rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3.5 hover:bg-brand/10 transition text-left w-full lg:hidden">
            <span class="size-11 rounded-xl bg-emerald-600 text-white grid place-items-center shrink-0" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>
            </span>
            <span class="text-base font-bold text-gray-900">{{ $messagesLabel }}</span>
        </button>

        <button type="button" @click="shareEmail()"
                class="flex items-center gap-3 rounded-2xl bg-brand/5 ring-1 ring-brand/15 px-4 py-3.5 hover:bg-brand/10 transition text-left w-full">
            <span class="size-11 rounded-xl bg-brand text-white grid place-items-center shrink-0" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16v12H4z"/><path d="m4 7 8 6 8-6"/></svg>
            </span>
            <span class="text-base font-bold text-gray-900">{{ $emailLabel }}</span>
        </button>

        <button type="button" @click="copyShare()"
                class="flex items-center gap-3 rounded-2xl bg-white ring-1 ring-brand/20 px-4 py-3.5 hover:bg-brand/5 transition text-left w-full">
            <span class="size-11 rounded-xl bg-gray-900 text-white grid place-items-center shrink-0" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
            </span>
            <span class="text-base font-bold text-gray-900">
                <span x-show="!copied">{{ $copyLabel }}</span>
                <span x-show="copied" x-cloak>{{ $copiedLabel }}</span>
            </span>
        </button>

        <button type="button" x-show="canNativeShare" x-cloak @click="shareMore()"
                class="flex items-center gap-3 rounded-2xl bg-white ring-1 ring-gray-200 px-4 py-3.5 hover:bg-gray-50 transition text-left w-full">
            <span class="size-11 rounded-xl bg-gray-100 text-brand grid place-items-center shrink-0" aria-hidden="true">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="2"/><circle cx="6" cy="12" r="2"/><circle cx="18" cy="19" r="2"/><path d="M8.6 13.5 15.4 17M15.4 7 8.6 10.5"/></svg>
            </span>
            <span class="text-base font-bold text-gray-900">{{ $moreLabel }}</span>
        </button>
    </div>
</x-site.action-panel>

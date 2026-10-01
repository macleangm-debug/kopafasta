<x-site.layout :title="brand_title($isSw ? 'Msaidizi wa Kopafasta' : 'Kopafasta Assistant')">
    @php
        $isSw = $isSw ?? str_starts_with(app()->getLocale(), 'sw');
        $guestFirstName = $guestFirstName ?? '';
        $guestLastName = $guestLastName ?? '';
        $guestPhone = $guestPhone ?? '';
        $guestName = trim($guestFirstName.' '.$guestLastName);
        $conversation = $conversation ?? null;
    @endphp

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10">
        <div class="mb-4">
            <a href="{{ route('site.support') }}" class="text-sm font-semibold text-brand hover:underline">
                ← {{ $isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Center' }}
            </a>
        </div>

        <x-site.ai-support-chat
            class="mb-4 w-full"
            :member-mode="false"
            :automation-mode="true"
            :force-human="false"
            :show-back-to-faqs="true"
            :back-to-faqs-label="$isSw ? 'Rudi Kituo cha Usaidizi' : 'Back to Help Center'"
            :speak-url="route('site.support.chat.speak')"
            :thread-url="route('site.support.chat.thread')"
            :automation-url="route('site.support.chat.automation')"
            :conversation="$conversation"
            :existing-messages="$conversation?->messages"
            :guest-name="$guestName"
            :guest-first-name="$guestFirstName"
            :guest-last-name="$guestLastName"
            :guest-phone="$guestPhone"
            :agent-label="$isSw ? 'Msaidizi wa Kopafasta' : 'Kopafasta Assistant'"
            :agent-subtitle="$isSw ? 'Msaada saa 24' : 'Help available 24/7'"
        />
    </section>
</x-site.layout>

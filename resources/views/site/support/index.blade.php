<x-site.layout :title="brand_title(__('site.support.title'))">
    <section class="bg-brand text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">{{ __('site.support.title') }}</h1>
            <p class="mt-3 text-white/80">{{ __('site.support.subtitle') }}</p>
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16">
        <x-site.ai-support-chat class="mb-8" :member-mode="false" />

        <div class="grid sm:grid-cols-3 gap-4 mb-8">
            <a href="tel:{{ preg_replace('/\s+/', '', brand('support_phone')) }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                <p class="text-2xl mb-2">📞</p>
                <p class="font-semibold">{{ __('site.nav.contact') }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ brand('support_phone') }}</p>
            </a>
            <a href="mailto:{{ brand('support_email') }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                <p class="text-2xl mb-2">✉️</p>
                <p class="font-semibold">Email</p>
                <p class="text-sm text-gray-500 mt-1">{{ brand('support_email') }}</p>
            </a>
            <a href="{{ route('site.feedback') }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                <p class="text-2xl mb-2">💬</p>
                <p class="font-semibold">{{ __('site.footer.feedback') }}</p>
                <p class="text-sm text-gray-500 mt-1">{{ __('site.feedback.subtitle') }}</p>
            </a>
        </div>

        <div class="text-center">
            <a href="{{ route('site.faq') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.footer.faq') }} →</a>
        </div>
    </section>
</x-site.layout>

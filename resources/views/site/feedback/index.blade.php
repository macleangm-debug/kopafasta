<x-site.layout :title="brand_title(__('site.feedback.title'))">
    <x-site.public-hero
        variant="minimal"
        :title="__('site.feedback.title')"
        :body="__('site.feedback.subtitle')"
    />

    <x-site.public-section narrow>
        <div class="text-center space-y-4">
            <button type="button" @click="$dispatch('open-feedback')"
                    class="inline-flex items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-8 py-3.5 rounded-xl shadow-sm">
                {{ __('site.feedback.submit') }}
            </button>
            <div>
                <a href="{{ route('site.faq') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-brand/20 px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted/50 transition">
                    {{ __('site.footer.faq') }}
                </a>
            </div>
        </div>
    </x-site.public-section>
</x-site.layout>

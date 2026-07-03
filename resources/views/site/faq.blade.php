<x-site.layout :title="brand_title(__('site.faq.title'))">
    <section class="bg-brand text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">{{ __('site.faq.title') }}</h1>
            <p class="mt-3 text-white/80 max-w-xl mx-auto">{{ __('site.faq.subtitle') }}</p>
        </div>
    </section>

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16" x-data="{ open: null, category: 'general' }">
        <div class="flex flex-wrap gap-2 mb-8">
            @foreach (__('site.faq.categories') as $key => $cat)
                <button type="button" @click="category = '{{ $key }}'; open = null"
                        :class="category === '{{ $key }}' ? 'bg-brand text-white' : 'bg-white ring-1 ring-gray-200 text-gray-600 hover:ring-brand/30'"
                        class="px-4 py-2 rounded-full text-sm font-medium transition">
                    {{ $cat['title'] }}
                </button>
            @endforeach
        </div>

        @foreach (__('site.faq.categories') as $key => $cat)
            <div x-show="category === '{{ $key }}'" x-cloak class="space-y-3">
                @foreach ($cat['items'] as $i => $item)
                    <div class="glass-card overflow-hidden">
                        <button type="button" @click="open = open === '{{ $key }}-{{ $i }}' ? null : '{{ $key }}-{{ $i }}'"
                                class="w-full px-5 py-4 flex items-center justify-between text-left font-medium text-sm hover:bg-brand-muted/30 transition">
                            <span>{{ $item['q'] }}</span>
                            <svg :class="open === '{{ $key }}-{{ $i }}' ? 'rotate-180' : ''" class="w-5 h-5 text-gray-400 transition shrink-0 ml-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                        <div x-show="open === '{{ $key }}-{{ $i }}'" x-collapse class="px-5 pb-4 text-sm text-gray-600 leading-relaxed border-t border-gray-100/80 pt-3">
                            {{ $item['a'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

        <div class="mt-12 glass-card p-8 text-center">
            <p class="font-semibold text-gray-900">{{ __('site.support.escalate') }}</p>
            <p class="mt-2 text-sm text-gray-600">{{ __('site.support.escalate_body') }}</p>
            <div class="mt-4 flex flex-wrap justify-center gap-3">
                <a href="{{ route('site.support') }}" class="inline-flex bg-brand text-white font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-light transition">{{ __('site.footer.support') }}</a>
                <a href="{{ route('site.feedback') }}" class="inline-flex ring-1 ring-brand/30 text-brand font-semibold px-5 py-2.5 rounded-xl text-sm hover:bg-brand-muted transition">{{ __('site.footer.feedback') }}</a>
            </div>
        </div>
    </section>
</x-site.layout>

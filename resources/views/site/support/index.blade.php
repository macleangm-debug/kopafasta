<x-site.layout :title="brand_title(__('site.support.title'))">
    @php
        $phones = support_phones();
        $emails = support_emails();
        $hotlineLabel = \App\Models\Setting::get('company.hotline_label') ?: __('site.nav.contact');
        $categories = app(\App\Http\Controllers\Site\FeedbackController::class)->categories();
    @endphp
    <x-site.public-hero
        variant="minimal"
        :title="__('site.support.title')"
        :body="__('site.support.subtitle')"
    />

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16" x-data="{ helpOpen: false, category: '', phase: 'form' }">
        <x-site.ai-support-chat class="mb-8" :member-mode="false" />

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach ($phones as $phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                    <span class="text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                    <p class="font-semibold text-gray-900 mt-2">{{ $hotlineLabel }}@if (count($phones) > 1) {{ $loop->iteration }}@endif</p>
                    <p class="text-sm text-gray-700 mt-1 font-medium">{{ $phone }}</p>
                </a>
            @endforeach
            @foreach ($emails as $email)
                <a href="mailto:{{ $email }}" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition">
                    <span class="text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                    <p class="font-semibold text-gray-900 mt-2">{{ __('site.feedback.email') }}@if (count($emails) > 1) {{ $loop->iteration }}@endif</p>
                    <p class="text-sm text-gray-700 mt-1 font-medium break-all">{{ $email }}</p>
                </a>
            @endforeach
            <button type="button" @click="helpOpen = true; phase = 'form'" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition text-left">
                <span class="text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                <p class="font-semibold text-gray-900 mt-2">{{ __('site.footer.feedback') }}</p>
                <p class="text-sm text-gray-700 mt-1">{{ __('site.feedback.subtitle') }}</p>
            </button>
        </div>

        <x-site.action-panel open="helpOpen" :title="__('site.feedback.title')" size="lg">
            @include('site.feedback._form', [
                'categories' => $categories,
                'formRef' => 'supportFeedbackForm',
                'categoryModel' => 'category',
                'phaseModel' => 'phase',
                'inlineType' => true,
            ])
        </x-site.action-panel>

        <div class="text-center">
            <a href="{{ route('site.faq') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-brand/20 px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted/50 transition">
                {{ __('site.footer.faq') }}
            </a>
        </div>
    </section>
</x-site.layout>

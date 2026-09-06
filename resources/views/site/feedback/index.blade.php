<x-site.layout :title="brand_title(__('site.feedback.title'))">
    @php
        $categories = $categories ?? app(\App\Http\Controllers\Site\FeedbackController::class)->categories();
        $openOnLoad = $openOnLoad ?? ($errors->any() || old('category') || session('status') || request()->boolean('open'));
        $success = session('status');
    @endphp

    <x-site.public-hero
        variant="minimal"
        :title="__('site.feedback.title')"
        :body="__('site.feedback.subtitle')"
    />

    <x-site.public-section narrow
             x-data="{
                open: {{ $openOnLoad ? 'true' : 'false' }},
                phase: @js($success ? 'done' : 'form'),
                category: @js(old('category', '')),
             }">
        <div class="text-center space-y-4">
            <button type="button" @click="open = true; phase = 'form'"
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

        <x-site.action-panel open="open" :title="__('site.feedback.title')" size="lg">
            <div x-show="phase === 'done'" x-cloak class="space-y-4 text-center py-4">
                <p class="text-sm text-emerald-800 font-semibold">{{ $success ?: __('site.feedback.success') }}</p>
                <button type="button" class="rounded-xl bg-brand text-white px-5 py-2.5 text-sm font-bold" @click="phase = 'form'">{{ __('site.feedback.submit') }}</button>
            </div>
            <div x-show="phase !== 'done'" x-cloak>
                @include('site.feedback._form', [
                    'categories' => $categories,
                    'formRef' => 'feedbackForm',
                    'categoryModel' => 'category',
                    'phaseModel' => 'phase',
                    'inlineType' => true,
                    'showStatusBanner' => false,
                ])
            </div>
        </x-site.action-panel>
    </x-site.public-section>
</x-site.layout>

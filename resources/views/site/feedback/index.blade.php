<x-site.layout :title="brand_title(__('site.feedback.title'))">
    @php
        $categories = $categories ?? app(\App\Http\Controllers\Site\FeedbackController::class)->categories();
        $openOnLoad = $errors->any() || old('category');
    @endphp

    <x-site.public-hero
        variant="minimal"
        :title="__('site.feedback.title')"
        :body="__('site.feedback.subtitle')"
    />

    <x-site.public-section narrow
             x-data="{
                open: {{ $openOnLoad ? 'true' : 'false' }},
                phase: 'form',
                category: @js(old('category', '')),
             }">
        @if (session('status'))
            <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ($categories as $key => $cat)
                <button type="button" @click="open = true; phase = 'form'; category = '{{ $key }}'"
                        class="rounded-2xl ring-1 ring-brand/15 bg-white px-4 py-4 text-left hover:bg-brand-muted/40 transition">
                    <p class="font-semibold text-gray-900">{{ $cat['label'] }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ $cat['description'] }}</p>
                </button>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <button type="button" @click="open = true; phase = 'form'"
                    class="inline-flex bg-brand hover:bg-brand-light text-white font-bold px-8 py-3 rounded-xl">
                {{ __('site.feedback.submit') }}
            </button>
        </div>

        <x-site.action-panel open="open" :title="__('site.feedback.title')" size="lg">
            @if ($errors->any())
                <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('site.feedback.post') }}" class="space-y-4" x-show="phase === 'form'" x-ref="feedbackForm"
                  @submit.prevent="phase = 'review'">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.choose_type') }}</label>
                    <select name="category" x-model="category" required
                            class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                        <option value="">{{ __('site.feedback.choose_type') }}</option>
                        @foreach ($categories as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.name') }}</label>
                        <input name="name" value="{{ old('name', auth()->user()?->name) }}" required
                               class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.email') }}</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}"
                               class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.phone') }}</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}"
                           class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" required
                           class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.message') }}</label>
                    <textarea name="message" rows="4" required
                              class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">{{ old('message') }}</textarea>
                </div>
                <button type="submit" class="w-full bg-brand text-white font-bold py-3 rounded-xl">{{ __('site.feedback.submit') }}</button>
            </form>

            <div x-show="phase === 'review'" x-cloak class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('site.feedback.review_body') }}</p>
                <div class="flex gap-2">
                    <button type="button" class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="phase = 'form'">{{ __('site.partner_apply.back') }}</button>
                    <button type="button" class="flex-1 rounded-xl bg-brand text-white py-3 text-sm font-bold"
                            @click="$refs.feedbackForm.removeAttribute('x-on:submit.prevent'); $refs.feedbackForm.submit()">
                        {{ __('site.feedback.confirm_submit') }}
                    </button>
                </div>
            </div>
        </x-site.action-panel>
    </x-site.public-section>
</x-site.layout>

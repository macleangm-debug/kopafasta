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

    <section class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16" x-data="{ helpOpen: false, helpKey: '', phase: 'form' }">
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
            <button type="button" @click="helpOpen = true; helpKey = ''; phase = 'form'" class="glass-card p-5 hover:ring-2 hover:ring-brand/20 transition text-left">
                <span class="text-brand-gold font-black tracking-[-0.14em]" aria-hidden="true">›››</span>
                <p class="font-semibold text-gray-900 mt-2">{{ __('site.footer.feedback') }}</p>
                <p class="text-sm text-gray-700 mt-1">{{ __('site.feedback.subtitle') }}</p>
            </button>
        </div>

        <div class="grid sm:grid-cols-2 gap-3 mb-8">
            @foreach ($categories as $key => $cat)
                <button type="button" @click="helpOpen = true; helpKey = '{{ $key }}'; phase = 'form'"
                        class="rounded-xl ring-1 ring-brand/15 bg-white px-4 py-3 text-left text-sm font-semibold text-gray-900 hover:bg-brand-muted/40">
                    {{ $cat['label'] }}
                </button>
            @endforeach
        </div>

        <x-site.action-panel open="helpOpen" :title="__('site.feedback.title')" size="lg">
            <form method="POST" action="{{ route('site.feedback.post') }}" class="space-y-4" x-show="phase === 'form'" x-ref="supportFeedbackForm"
                  @submit.prevent="phase = 'review'">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.choose_type') }}</label>
                    <select name="category" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10" x-model="helpKey" required>
                        <option value="">{{ __('site.feedback.choose_type') }}</option>
                        @foreach ($categories as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.name') }}</label>
                        <input name="name" value="{{ old('name', auth()->user()?->name) }}" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.email') }}</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.phone') }}</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.message') }}</label>
                    <textarea name="message" rows="4" required class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">{{ old('message') }}</textarea>
                </div>
                <button type="submit" class="w-full bg-brand text-white font-bold py-3 rounded-xl">{{ __('site.feedback.submit') }}</button>
            </form>
            <div x-show="phase === 'review'" x-cloak class="space-y-4">
                <p class="text-sm text-gray-600">{{ __('site.feedback.review_body') }}</p>
                <div class="flex gap-2">
                    <button type="button" class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="phase = 'form'">{{ __('site.partner_apply.back') }}</button>
                    <button type="button" class="flex-1 rounded-xl bg-brand text-white py-3 text-sm font-bold"
                            @click="$refs.supportFeedbackForm.removeAttribute('x-on:submit.prevent'); $refs.supportFeedbackForm.submit()">
                        {{ __('site.feedback.confirm_submit') }}
                    </button>
                </div>
            </div>
        </x-site.action-panel>

        <div class="text-center">
            <a href="{{ route('site.faq') }}" class="text-sm font-semibold text-brand hover:underline">{{ __('site.footer.faq') }} →</a>
        </div>
    </section>
</x-site.layout>

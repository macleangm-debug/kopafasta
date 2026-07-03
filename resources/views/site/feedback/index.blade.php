<x-site.layout :title="brand_title(__('site.feedback.title'))">
    <section class="bg-brand text-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-14 text-center">
            <h1 class="text-3xl sm:text-4xl font-bold">{{ __('site.feedback.title') }}</h1>
            <p class="mt-3 text-white/80">{{ __('site.feedback.subtitle') }}</p>
        </div>
    </section>

    <section class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16"
             x-data="{ category: @js(old('category', '')) }">
        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('site.feedback.post') }}" class="space-y-8">
            @csrf

            <div>
                <h2 class="font-bold text-lg mb-4">{{ __('site.feedback.choose_type') }}</h2>
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach ($categories as $key => $cat)
                        <label class="cursor-pointer glass-card p-4 transition hover:ring-2 hover:ring-brand/20"
                               :class="category === '{{ $key }}' ? 'ring-2 ring-brand bg-brand-muted/40' : ''">
                            <input type="radio" name="category" value="{{ $key }}" class="sr-only" x-model="category" required>
                            <span class="font-semibold text-gray-900 block">{{ $cat['label'] }}</span>
                            <span class="text-xs text-gray-500 mt-1 block">{{ $cat['description'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div x-show="category" x-cloak class="glass-card p-6 space-y-5">
                <h2 class="font-bold text-lg">{{ __('site.feedback.your_details') }}</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.feedback.name') }}</label>
                        <input name="name" value="{{ old('name', auth()->user()?->name) }}" required class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.feedback.email') }}</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.feedback.phone') }}</label>
                    <input type="tel" name="phone" value="{{ old('phone') }}" class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.feedback.subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" required class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                </div>
                <div x-show="['complaint', 'technical', 'loan_inquiry'].includes(category)" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.feedback.reference') }}</label>
                    <input name="reference" value="{{ old('reference') }}" class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('site.feedback.message') }}</label>
                    <textarea name="message" rows="5" required class="w-full rounded-xl border-gray-200 px-3 py-2.5 text-sm focus:border-brand focus:ring-brand/10">{{ old('message') }}</textarea>
                </div>
                <button type="submit" class="w-full sm:w-auto bg-brand hover:bg-brand-light text-white font-bold px-8 py-3 rounded-xl transition">
                    {{ __('site.feedback.submit') }}
                </button>
            </div>
        </form>
    </section>
</x-site.layout>

@php
    $layout = view()->exists('components.site.vendor-layout') ? 'x-site.vendor-layout' : null;
@endphp
<x-site.vendor-layout :title="__('site.feedback.title')" active="support">
    @php $isSw = str_starts_with(app()->getLocale(), 'sw'); @endphp
    <div class="mb-4">
        <a href="{{ $supportHome }}" class="text-sm font-semibold text-brand hover:underline">← Support Home</a>
    </div>

    @if (session('status'))
        <div class="max-w-xl rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 px-5 py-6 space-y-4">
            <p class="text-sm font-semibold text-emerald-950">{{ session('status') }}</p>
            <a href="{{ $supportHome }}" class="inline-flex rounded-xl bg-brand text-white text-sm font-semibold px-4 py-2.5">
                {{ $isSw ? 'Rudi Support' : 'Back to Support' }}
            </a>
        </div>
    @else
        <div class="max-w-xl rounded-2xl bg-white ring-1 ring-brand/10 shadow-sm p-5 sm:p-6"
             x-data="{ category: @js(old('category', '')) }">
            <h1 class="text-xl font-bold text-gray-900">{{ __('site.feedback.title') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('site.feedback.subtitle') }}</p>
            <p class="mt-3 text-xs text-slate-500">
                {{ $isSw ? 'Unawasilisha kama' : 'Submitting as' }}
                <span class="font-semibold">{{ auth()->user()?->name }}</span>
            </p>
            <form method="POST" action="{{ route('site.feedback.post') }}" class="mt-5 space-y-4" data-no-draft>
                @csrf
                <input type="hidden" name="from" value="partner">
                <input type="hidden" name="name" value="{{ auth()->user()?->name }}">
                <label class="block text-sm font-semibold">{{ __('site.feedback.choose_type') }}
                    <select name="category" x-model="category" required class="mt-1 w-full rounded-xl border-gray-300 text-sm">
                        <option value="">{{ __('site.feedback.choose_type') }}</option>
                        @foreach ($categories as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold">{{ __('site.feedback.subject') }}
                    <input name="subject" value="{{ old('subject') }}" required class="mt-1 w-full rounded-xl border-gray-300 text-sm px-3 py-3">
                </label>
                <label class="block text-sm font-semibold">{{ __('site.feedback.message') }}
                    <textarea name="message" rows="5" required class="mt-1 w-full rounded-xl border-gray-300 text-sm px-3 py-3">{{ old('message') }}</textarea>
                </label>
                <button class="rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5">{{ __('site.feedback.submit') }}</button>
            </form>
        </div>
    @endif
</x-site.vendor-layout>

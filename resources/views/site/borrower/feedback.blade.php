<x-site.borrower-layout :title="brand_title(__('site.feedback.title'))" active="support" content-width="wide">
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
                <span class="font-semibold text-slate-700">{{ auth()->user()?->name }}</span>
                — {{ $isSw ? 'hatutauliza tena jina/simu/barua pepe.' : 'we will not ask again for name, phone, or email.' }}
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('site.feedback.post') }}" class="mt-5 space-y-4" data-no-draft>
                @csrf
                <input type="hidden" name="from" value="borrower">
                <input type="hidden" name="name" value="{{ auth()->user()?->name }}">
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.choose_type') }}</label>
                    <select name="category" x-model="category" required class="w-full rounded-xl border-gray-300 text-sm">
                        <option value="">{{ __('site.feedback.choose_type') }}</option>
                        @foreach ($categories as $key => $cat)
                            <option value="{{ $key }}">{{ $cat['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.subject') }}</label>
                    <input name="subject" value="{{ old('subject') }}" required maxlength="200"
                           class="w-full rounded-xl border-gray-300 text-sm px-3 py-3">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.message') }}</label>
                    <textarea name="message" rows="5" required maxlength="5000"
                              class="w-full rounded-xl border-gray-300 text-sm px-3 py-3">{{ old('message') }}</textarea>
                </div>
                <div x-show="['complaint','technical','loan_inquiry'].includes(category)" x-cloak>
                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.reference') }}</label>
                    <input name="reference" value="{{ old('reference') }}" maxlength="80"
                           class="w-full rounded-xl border-gray-300 text-sm px-3 py-3"
                           placeholder="APP-… / Loan # (optional)">
                </div>
                <button class="rounded-xl bg-brand text-white text-sm font-semibold px-5 py-2.5">{{ __('site.feedback.submit') }}</button>
            </form>
        </div>
    @endif
</x-site.borrower-layout>

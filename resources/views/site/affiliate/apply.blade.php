<x-site.layout :title="brand_title(__('site.affiliate_apply.title'))">
    <div class="max-w-2xl mx-auto py-10 px-4">
        <p class="text-xs uppercase tracking-widest text-amber-600 mb-2">{{ brand_name() }}</p>
        <h1 class="text-3xl font-bold tracking-tight mb-2">{{ __('site.affiliate_apply.title') }}</h1>
        <p class="text-sm text-gray-600 mb-8">{{ __('site.affiliate_apply.subtitle') }}</p>

        @if (session('status'))
            <div class="mb-6 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('site.affiliate.apply.post') }}" class="bg-white rounded-2xl ring-1 ring-gray-200 p-6 sm:p-8 space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.full_name') }}</label>
                <input name="full_name" value="{{ old('full_name') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.phone') }}</label>
                    <input name="phone" value="{{ old('phone') }}" required placeholder="+255…" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.business_name') }}</label>
                    <input name="business_name" value="{{ old('business_name') }}" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.region') }}</label>
                    <select name="region" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm">
                        <option value="">{{ __('site.affiliate_apply.select_region') }}</option>
                        @foreach ($regions as $region)
                            <option value="{{ $region }}" @selected(old('region') === $region)>{{ $region }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('site.affiliate_apply.message') }}</label>
                <textarea name="message" rows="4" class="w-full rounded-lg border-gray-300 ring-1 ring-gray-200 px-3 py-2.5 text-sm" placeholder="{{ __('site.affiliate_apply.message_placeholder') }}">{{ old('message') }}</textarea>
            </div>
            <button class="bg-amber-500 hover:bg-amber-400 text-gray-900 font-semibold px-6 py-2.5 rounded-full text-sm">{{ __('site.affiliate_apply.submit') }}</button>
        </form>
    </div>
</x-site.layout>

@php
    $categories = $categories ?? [];
    $formRef = $formRef ?? 'feedbackForm';
    $categoryModel = $categoryModel ?? 'category';
    $phaseModel = $phaseModel ?? 'phase';
    $inlineType = $inlineType ?? false;
    $showStatusBanner = $showStatusBanner ?? true;
    $categoryOptions = collect($categories)->mapWithKeys(fn ($cat, $key) => [$key => $cat['label']])->all();
@endphp

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
        <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if ($showStatusBanner && session('status'))
    <div class="mb-4 rounded-xl bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-900" role="status">
        {{ session('status') }}
    </div>
@endif

<form method="POST" action="{{ route('site.feedback.post') }}" class="space-y-4" x-show="{{ $phaseModel }} === 'form'" x-ref="{{ $formRef }}"
      @submit.prevent="{{ $phaseModel }} = 'review'">
    @csrf
    <input type="hidden" name="category" :value="{{ $categoryModel }}">
    <div>
        @if ($inlineType)
            {{-- Same-surface type picker: mobile list + desktop dropdown, no second sheet. --}}
            <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.choose_type') }}</label>
            <div class="space-y-1 max-h-48 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-1.5 lg:hidden">
                @foreach ($categoryOptions as $key => $label)
                    <button type="button"
                            @click="{{ $categoryModel }} = '{{ $key }}'"
                            class="w-full text-left flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm hover:bg-brand-muted/50"
                            :class="{{ $categoryModel }} === '{{ $key }}' ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800'">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
            <div class="hidden lg:block relative" x-data="{ desktopOpen: false }" @keydown.escape.window="desktopOpen = false">
                <button type="button" @click="desktopOpen = !desktopOpen"
                        class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-800 hover:border-brand/30">
                    <span class="flex-1 text-left truncate"
                          x-text="({{ $categoryModel }} && {{ \Illuminate\Support\Js::from($categoryOptions) }}[{{ $categoryModel }}]) || @js(__('site.feedback.choose_type'))"></span>
                    <svg class="w-4 h-4 text-gray-400 shrink-0" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                </button>
                <div x-cloak x-show="desktopOpen" @click.outside="desktopOpen = false"
                     class="absolute z-30 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-64 overflow-y-auto">
                    @foreach ($categoryOptions as $key => $label)
                        <button type="button" @click="{{ $categoryModel }} = '{{ $key }}'; desktopOpen = false"
                                class="w-full text-left px-4 py-2.5 text-sm text-gray-800 hover:bg-brand-muted"
                                :class="{{ $categoryModel }} === '{{ $key }}' ? 'bg-brand-muted text-brand font-semibold' : ''">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        @else
            <x-site.sheet-select
                name="category_ui"
                :label="__('site.feedback.choose_type')"
                :options="$categoryOptions"
                :value="old('category', '')"
                :model="$categoryModel"
                :required="true"
                :placeholder="__('site.feedback.choose_type')"
            />
        @endif
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
        <x-site.phone-input
            name="phone"
            :label="__('site.feedback.phone')"
            :value="old('phone')"
            variant="rounded"
            :allow-country-change="true"
            :help="false"
        />
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
    <button type="submit" class="w-full bg-brand text-white font-bold py-3 rounded-xl sticky bottom-0">{{ __('site.feedback.submit') }}</button>
</form>

<div x-show="{{ $phaseModel }} === 'review'" x-cloak class="space-y-4">
    <p class="text-sm text-gray-600">{{ __('site.feedback.review_body') }}</p>
    <div class="flex gap-2">
        <button type="button" class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="{{ $phaseModel }} = 'form'">{{ __('site.partner_apply.back') }}</button>
        <button type="button" class="flex-1 rounded-xl bg-brand text-white py-3 text-sm font-bold"
                @click="$refs.{{ $formRef }}.removeAttribute('x-on:submit.prevent'); $refs.{{ $formRef }}.submit()">
            {{ __('site.feedback.confirm_submit') }}
        </button>
    </div>
</div>

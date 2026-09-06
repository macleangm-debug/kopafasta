@php
    $categories = $categories ?? app(\App\Http\Controllers\Site\FeedbackController::class)->categories();
    $openOnLoad = (bool) ($openOnLoad ?? false);
    $successMessage = session('status');
    $categoryOptions = collect($categories)->mapWithKeys(fn ($cat, $key) => [$key => $cat['label']])->all();
    $old = [
        'category' => old('category', ''),
        'name' => old('name', auth()->user()?->name),
        'email' => old('email', auth()->user()?->email),
        'phone' => old('phone'),
        'subject' => old('subject'),
        'message' => old('message'),
    ];
@endphp

<div
    x-data="{
        open: {{ ($openOnLoad || $errors->any() || filled($successMessage)) ? 'true' : 'false' }},
        phase: @js(filled($successMessage) ? 'done' : ($errors->any() ? 'form' : 'form')),
        category: @js($old['category']),
        typeOpen: false,
        categoryOptions: @js($categoryOptions),
        labelFor(val) {
            return (val && this.categoryOptions[val]) ? this.categoryOptions[val] : @js(__('site.feedback.choose_type'));
        },
        openPanel() {
            this.open = true;
            if (this.phase === 'done' && ! @js(filled($successMessage))) this.phase = 'form';
        },
        closePanel() { this.open = false; this.typeOpen = false; },
        goReview() {
            if (! this.category) return;
            this.phase = 'review';
        },
        goForm() { this.phase = 'form'; },
        submitForm() {
            const form = this.$refs.feedbackForm;
            if (! form) return;
            form.removeAttribute('x-on:submit.prevent');
            form.submit();
        }
    }"
    x-effect="
        if (typeof document === 'undefined') return;
        document.documentElement.classList.toggle('overflow-hidden', open);
        document.body.classList.toggle('overflow-hidden', open);
    "
    @keydown.escape.window="if (open) closePanel()"
>
    @if ($showTrigger ?? true)
        <div class="text-center space-y-4">
            <button type="button" @click="openPanel()"
                    class="inline-flex items-center justify-center gap-2 bg-brand hover:bg-brand-light text-white font-bold px-8 py-3.5 rounded-xl shadow-sm">
                {{ __('site.feedback.submit') }}
            </button>
            @if ($showFaqLink ?? true)
                <div>
                    <a href="{{ route('site.faq') }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-white ring-1 ring-brand/20 px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-muted/50 transition">
                        {{ __('site.footer.faq') }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    <template x-teleport="body">
        <div x-show="open" class="fixed inset-0 z-[10050]" role="dialog" aria-modal="true" style="display: none;">
            <div class="absolute inset-0 bg-black/40" @click="closePanel()" x-transition.opacity></div>
            <div class="absolute inset-x-0 bottom-0 lg:inset-auto lg:left-1/2 lg:top-1/2 lg:-translate-x-1/2 lg:-translate-y-1/2
                        w-full lg:max-w-xl max-h-[min(92dvh,760px)] flex flex-col rounded-t-2xl lg:rounded-3xl bg-white shadow-[0_-8px_40px_rgba(0,0,0,0.18)] lg:shadow-2xl lg:ring-1 lg:ring-gray-200"
                 style="padding-bottom: env(safe-area-inset-bottom, 0px)"
                 @click.stop
                 x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full lg:translate-y-0 lg:opacity-0 lg:scale-95"
                 x-transition:enter-end="translate-y-0 lg:opacity-100 lg:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 lg:opacity-100"
                 x-transition:leave-end="translate-y-full lg:opacity-0 lg:scale-95">
                <div class="flex justify-center pt-3 pb-1 shrink-0 lg:hidden">
                    <div class="w-10 h-1 rounded-full bg-gray-300"></div>
                </div>
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 shrink-0">
                    <h2 class="text-base font-bold text-gray-900">{{ __('site.feedback.title') }}</h2>
                    <button type="button" @click="closePanel()" class="p-2 -mr-2 rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Close">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto overscroll-contain px-5 py-4">
                    <div x-show="phase === 'done'" class="space-y-4 text-center py-4">
                        <p class="text-sm text-emerald-800 font-semibold">{{ $successMessage ?: __('site.feedback.success') }}</p>
                        <button type="button" class="rounded-xl bg-brand text-white px-5 py-2.5 text-sm font-bold" @click="goForm()">{{ __('site.feedback.submit') }}</button>
                    </div>

                    <div x-show="phase === 'form'">
                        @if ($errors->any())
                            <div class="mb-4 rounded-xl bg-red-50 ring-1 ring-red-200 px-4 py-3 text-sm text-red-700">
                                <ul class="list-disc ml-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('site.feedback.post') }}" class="space-y-4" x-ref="feedbackForm"
                              @submit.prevent="goReview()">
                            @csrf
                            <input type="hidden" name="category" :value="category">

                            <div class="relative">
                                <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.choose_type') }}</label>
                                <div class="lg:hidden space-y-1 max-h-48 overflow-y-auto rounded-xl ring-1 ring-gray-200 p-1.5">
                                    @foreach ($categoryOptions as $key => $label)
                                        <button type="button" @click="category = '{{ $key }}'"
                                                class="w-full text-left px-3 py-2.5 rounded-lg text-sm"
                                                :class="category === '{{ $key }}' ? 'bg-brand-muted text-brand font-semibold ring-1 ring-brand/20' : 'text-gray-800 hover:bg-brand-muted/50'">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                                <div class="hidden lg:block relative">
                                    <button type="button" @click="typeOpen = !typeOpen"
                                            class="w-full inline-flex items-center gap-3 rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm font-medium text-gray-800">
                                        <span class="flex-1 text-left truncate" x-text="labelFor(category)"></span>
                                        <svg class="w-4 h-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path d="M5 8l5 5 5-5z"/></svg>
                                    </button>
                                    <div x-show="typeOpen" @click.outside="typeOpen = false" class="absolute z-30 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-xl py-1 max-h-64 overflow-y-auto">
                                        @foreach ($categoryOptions as $key => $label)
                                            <button type="button" @click="category = '{{ $key }}'; typeOpen = false"
                                                    class="w-full text-left px-4 py-2.5 text-sm"
                                                    :class="category === '{{ $key }}' ? 'bg-brand-muted text-brand font-semibold' : 'text-gray-800 hover:bg-brand-muted'">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            <div class="grid sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.name') }}</label>
                                    <input name="name" value="{{ $old['name'] }}" required
                                           class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.email') }}</label>
                                    <input type="email" name="email" value="{{ $old['email'] }}"
                                           class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                                </div>
                            </div>

                            <x-site.phone-input
                                name="phone"
                                :label="__('site.feedback.phone')"
                                :value="$old['phone']"
                                variant="rounded"
                                :allow-country-change="true"
                                :help="false"
                            />

                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.subject') }}</label>
                                <input name="subject" value="{{ $old['subject'] }}" required
                                       class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-1.5">{{ __('site.feedback.message') }}</label>
                                <textarea name="message" rows="4" required
                                          class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-brand focus:ring-2 focus:ring-brand/10">{{ $old['message'] }}</textarea>
                            </div>
                            <button type="submit" class="w-full bg-brand text-white font-bold py-3 rounded-xl sticky bottom-0">{{ __('site.feedback.submit') }}</button>
                        </form>
                    </div>

                    <div x-show="phase === 'review'" class="space-y-4">
                        <p class="text-sm text-gray-600">{{ __('site.feedback.review_body') }}</p>
                        <div class="flex gap-2">
                            <button type="button" class="flex-1 rounded-xl ring-1 ring-gray-200 py-3 text-sm font-semibold" @click="goForm()">{{ __('site.partner_apply.back') }}</button>
                            <button type="button" class="flex-1 rounded-xl bg-brand text-white py-3 text-sm font-bold" @click="submitForm()">{{ __('site.feedback.confirm_submit') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>
